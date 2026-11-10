<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\HisAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/** "จดจำการเข้าสู่ระบบ" on the login page: a user who ticked it stays signed in after the session is gone. */
class RememberMeTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seedDrugCodeSets = false;

    private function localUser(): User
    {
        return User::firstOrCreate(['username' => 'pharmacist1'], [
            'name' => 'Pharmacist', 'email' => 'pharmacist1@example.test', 'password' => 'secret-pass-1',
        ]);
    }

    /** The cookie a browser would keep: login, then return only the "remember me" cookie. */
    private function loginAndKeepCookie(bool $remember): ?string
    {
        $this->localUser();
        $response = $this->post(route('login'), [
            'username' => 'pharmacist1', 'password' => 'secret-pass-1', 'remember' => $remember ? 'on' : null,
        ]);
        $response->assertRedirect();

        foreach ($response->headers->getCookies() as $cookie) {
            if (str_starts_with($cookie->getName(), 'remember_web_')) {
                return $cookie->getValue();
            }
        }

        return null;
    }

    /** Next visit: the session is gone (browser closed / expired), only the cookie is sent back. */
    private function visitWithOnlyCookie(?string $value)
    {
        Auth::forgetGuards();
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $request = $this->withUnencryptedCookie($this->recallerName(), $value ?? '');

        return $request->get(route('dashboard'));
    }

    private function recallerName(): string
    {
        return Auth::guard()->getRecallerName();
    }

    public function test_ticking_the_box_sets_a_remember_cookie_and_it_signs_the_user_back_in(): void
    {
        $cookie = $this->loginAndKeepCookie(true);
        $this->assertNotNull($cookie, 'no remember cookie was issued');

        $this->visitWithOnlyCookie($cookie)->assertOk();
    }

    public function test_without_ticking_the_box_no_cookie_is_issued(): void
    {
        $this->assertNull($this->loginAndKeepCookie(false));
    }

    /** Production signs in through HOSxP; stand in for it with the real local-user sync. */
    private function hosxpLogin(): ?string
    {
        $real = app(HisAuthService::class);
        $this->mock(HisAuthService::class, function ($mock) use ($real) {
            $mock->shouldReceive('isAvailable')->andReturn(true);
            $mock->shouldReceive('attempt')->andReturnUsing(fn (string $username) => $real->syncLocalUser($username, 'Officer'));
        });

        $response = $this->post(route('login'), ['username' => 'officer1', 'password' => 'any', 'remember' => 'on']);
        $response->assertRedirect();
        foreach ($response->headers->getCookies() as $cookie) {
            if (str_starts_with($cookie->getName(), 'remember_web_')) {
                return $cookie->getValue();
            }
        }

        return null;
    }

    public function test_logging_in_again_from_another_browser_keeps_the_first_remember_cookie_valid(): void
    {
        $first = $this->hosxpLogin();
        $this->assertNotNull($first);

        $this->hosxpLogin(); // the same officer signs in on another PC (HOSxP login runs the sync again)

        $this->visitWithOnlyCookie($first)->assertOk();
    }

    public function test_the_synced_account_gets_an_unknowable_password_once_and_keeps_it(): void
    {
        $his = app(HisAuthService::class);

        $first = $his->syncLocalUser('officer1', 'Officer One');
        $hash = $first->password;
        $second = $his->syncLocalUser('officer1', 'Officer One (renamed)');

        $this->assertSame($hash, $second->fresh()->password);
        $this->assertSame('Officer One (renamed)', $second->fresh()->name);
        $this->assertSame('officer1@rdu.local', $second->fresh()->email);
    }

    public function test_a_local_account_with_a_known_password_loses_it_when_first_taken_over_by_hosxp(): void
    {
        $local = $this->localUser(); // password "secret-pass-1", email not @rdu.local

        app(HisAuthService::class)->syncLocalUser('pharmacist1', 'Pharmacist');

        $this->assertFalse(\Illuminate\Support\Facades\Hash::check('secret-pass-1', $local->fresh()->password));
    }
}
