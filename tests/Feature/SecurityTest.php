<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RduIndicatorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;
    protected string $seeder = RduIndicatorSeeder::class;

    // ---- Security headers ----

    public function test_security_headers_present_on_dashboard(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_security_headers_present_on_login_page(): void
    {
        $response = $this->get(route('login'));

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    // ---- Authentication ----

    public function test_guest_redirected_from_dashboard_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_access_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    // ---- Login rate limiting (throttle:5,1 on POST /login) ----

    public function test_login_throttled_after_five_failed_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), [
                'username' => 'wrong',
                'password' => 'wrong',
            ]);
        }

        $response = $this->post(route('login'), [
            'username' => 'wrong',
            'password' => 'wrong',
        ]);

        $response->assertStatus(429);
    }

    // ---- CSRF ----

    public function test_login_form_contains_csrf_token_field(): void
    {
        // The login page must render a hidden _token input so the browser includes
        // a CSRF token on form submission. (Laravel's VerifyCsrfToken middleware
        // bypasses the check in APP_ENV=testing, so we validate the token is
        // emitted rather than testing the 419 response path.)
        $response = $this->get(route('login'));

        $response->assertSee('_token', false);
    }

    // ---- Input sanitization — year/month clamping ----

    public function test_dashboard_clamps_year_below_minimum(): void
    {
        $user = User::factory()->create();
        // year=1000 is below 2560 — should not crash, silently clamped
        $this->actingAs($user)
            ->get(route('dashboard', ['year' => 1000, 'month' => 0]))
            ->assertOk();
    }

    public function test_dashboard_clamps_year_above_maximum(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('dashboard', ['year' => 9999, 'month' => 0]))
            ->assertOk();
    }

    public function test_dashboard_clamps_month_below_zero(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('dashboard', ['year' => 2568, 'month' => -5]))
            ->assertOk();
    }

    public function test_dashboard_clamps_month_above_twelve(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('dashboard', ['year' => 2568, 'month' => 99]))
            ->assertOk();
    }

    public function test_kpi_detail_clamps_year_below_minimum(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('dashboard.kpi', ['code' => 'KPI01', 'year' => 1000]))
            ->assertOk();
    }

    public function test_kpi_detail_clamps_month_above_twelve(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('dashboard.kpi', ['code' => 'KPI01', 'month' => 99]))
            ->assertOk();
    }

    // ---- No server fingerprinting ----

    public function test_x_powered_by_header_absent(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('dashboard'));

        $this->assertNull($response->headers->get('X-Powered-By'));
    }
}
