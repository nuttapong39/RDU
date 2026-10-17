<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RduCalculationService;
use Database\Seeders\RduIndicatorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;
    protected string $seeder = RduIndicatorSeeder::class;

    // ---- Auth ----

    public function test_guest_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_login_page_loads(): void
    {
        $this->get(route('login'))->assertOk()->assertViewIs('auth.login');
    }

    public function test_valid_credentials_redirect_to_dashboard(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);
        $response = $this->post(route('login'), [
            'username' => $user->username,
            'password' => 'password',
        ]);
        // HOSxP config may or may not exist — accept either redirect
        $response->assertRedirectContains('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_return_error(): void
    {
        $user = User::factory()->create();
        $response = $this->post(route('login'), [
            'username' => $user->username,
            'password' => 'wrong_password',
        ]);
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_logout_clears_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // ---- Dashboard content ----

    public function test_dashboard_shows_the_active_kpi_cards(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();
        // Active KPI codes should appear in the page
        foreach (['KPI01', 'KPI05', 'KPI09', 'KPI13'] as $code) {
            $response->assertSee($code);
        }
    }

    public function test_dashboard_shows_summary_bar(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertSeeInOrder(['ผ่านเกณฑ์', 'ใกล้เป้าหมาย', 'ไม่ผ่านเกณฑ์']);
    }

    public function test_dashboard_filter_accepts_year_and_month(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('dashboard', ['year' => 2568, 'month' => 1]));
        $response->assertOk()->assertSee('2568');
    }

    public function test_kpi_detail_page_loads_for_valid_code(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('dashboard.kpi', 'KPI01'));
        $response->assertOk()->assertViewIs('dashboard.kpi');
    }

    public function test_kpi_detail_page_returns_404_for_invalid_code(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('dashboard.kpi', 'INVALID'))->assertNotFound();
    }

    // ---- RduCalculationService unit tests ----

    public function test_status_pass_higher_direction(): void
    {
        $svc = app(RduCalculationService::class);
        $this->assertSame('pass', $svc->getStatus(85.0, 80.0, 'higher'));
    }

    public function test_status_fail_higher_direction(): void
    {
        $svc = app(RduCalculationService::class);
        $this->assertSame('fail', $svc->getStatus(70.0, 80.0, 'higher'));
    }

    public function test_status_warning_within_5pct(): void
    {
        $svc = app(RduCalculationService::class);
        $this->assertSame('warning', $svc->getStatus(76.0, 80.0, 'higher'));
    }

    public function test_status_pass_lower_direction(): void
    {
        $svc = app(RduCalculationService::class);
        $this->assertSame('pass', $svc->getStatus(18.0, 20.0, 'lower'));
    }

    public function test_status_fail_lower_direction(): void
    {
        $svc = app(RduCalculationService::class);
        $this->assertSame('fail', $svc->getStatus(30.0, 20.0, 'lower'));
    }

    public function test_status_na_when_null(): void
    {
        $svc = app(RduCalculationService::class);
        $this->assertSame('na', $svc->getStatus(null, 80.0, 'higher'));
    }

    public function test_summarize_counts_correctly(): void
    {
        $svc = app(RduCalculationService::class);
        $result = $svc->summarize(['pass', 'pass', 'fail', 'warning', 'na', 'pass']);
        $this->assertSame(['pass' => 3, 'warning' => 1, 'fail' => 1, 'na' => 1], $result);
    }

    public function test_fiscal_year_is_correct_for_current_date(): void
    {
        $svc = app(RduCalculationService::class);
        $fy  = $svc->currentFiscalYear();
        $this->assertGreaterThan(2567, $fy);
        $this->assertLessThan(2575, $fy);
    }
}
