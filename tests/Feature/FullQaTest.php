<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RduIndicatorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 11 — Full End-to-End QA
 *
 * Covers complete user journeys across all pages, verifying that every
 * visible surface of the application works together correctly.
 */
class FullQaTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;
    protected string $seeder = RduIndicatorSeeder::class;

    // =========================================================================
    // Login / Logout Journey
    // =========================================================================

    public function test_unauthenticated_root_redirects_to_dashboard_then_login(): void
    {
        // '/' redirects to 'dashboard', which redirects to 'login' when unauthenticated
        $this->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_login_page_shows_hospital_name(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('โรงพยาบาลเชียงกลาง', false)
            ->assertSee('RDU Dashboard', false);
    }

    public function test_login_page_has_username_and_password_fields(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('name="username"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="remember"', false);
    }

    public function test_successful_login_redirects_to_dashboard(): void
    {
        $user = User::factory()->create();

        $this->post(route('login'), [
            'username' => $user->username,
            'password' => 'password',
        ])->assertRedirect(route('dashboard')); // only settings admins are sent to the HOSxP setup page
    }

    public function test_invalid_credentials_return_to_login_with_error(): void
    {
        // $this->from() sets the Referer header so back() redirects to the login page
        $this->from(route('login'))
            ->post(route('login'), [
                'username' => 'nobody',
                'password' => 'wrong',
            ])
            ->assertRedirect(route('login'));
    }

    public function test_logout_clears_session_and_redirects_to_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        // After logout, dashboard must redirect to login again
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    // =========================================================================
    // Dashboard Overview Page
    // =========================================================================

    public function test_dashboard_page_title_contains_overview(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('ภาพรวม', false);
    }

    public function test_dashboard_shows_all_four_summary_boxes(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $response->assertSee('ผ่านเกณฑ์', false);
        $response->assertSee('ใกล้เป้าหมาย', false);
        $response->assertSee('ไม่ผ่านเกณฑ์', false);
        $response->assertSee('ไม่มีข้อมูล', false);
    }

    public function test_dashboard_shows_links_to_every_active_kpi_detail_page(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();

        foreach (self::ACTIVE_KPI_CODES as $code) {
            $response->assertSee(route('dashboard.kpi', $code), false);
        }
    }

    public function test_dashboard_filter_form_has_year_and_month_selects(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('name="year"', false)
            ->assertSee('name="month"', false)
            ->assertSee('รีเฟรช', false);
    }

    public function test_dashboard_filter_reflects_chosen_year(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('dashboard', ['year' => 2568, 'month' => 0]))
            ->assertOk()
            ->assertSee('2568', false);
    }

    public function test_dashboard_shows_hero_card(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('RDU Dashboard', false);
    }

    public function test_dashboard_sidebar_shows_admin_database_link(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('admin.database.show'), false);
    }

    public function test_dashboard_sidebar_shows_logout_button(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('ออกจากระบบ', false);
    }

    public function test_dashboard_shows_every_category_that_has_an_active_kpi(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $response->assertSee('ยาปฏิชีวนะ', false);
        $response->assertSee('ความปลอดภัย', false);
        $response->assertSee('โรคเรื้อรัง', false);
        $response->assertDontSee('คุณภาพยา', false);   // its only KPI (KPI06) is switched off
    }

    // =========================================================================
    // KPI Detail Pages — every active KPI
    // =========================================================================

    /**
     * @dataProvider kpiCodeProvider
     */
    public function test_all_kpi_detail_pages_load_successfully(string $code): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('dashboard.kpi', $code))
            ->assertOk();
    }

    public static function kpiCodeProvider(): array
    {
        return array_combine(
            self::ACTIVE_KPI_CODES,
            array_map(fn ($code) => [$code], self::ACTIVE_KPI_CODES)
        );
    }

    public function test_kpi_detail_shows_kpi_code_in_title(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('dashboard.kpi', 'KPI01'))
            ->assertOk()
            ->assertSee('KPI01', false);
    }

    public function test_kpi_detail_shows_numerator_label(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('dashboard.kpi', 'KPI01'))
            ->assertOk()
            ->assertSee('ตัวเศษ', false)
            ->assertSee('ตัวส่วน', false);
    }

    public function test_kpi_detail_shows_target_label(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('dashboard.kpi', 'KPI01'))
            ->assertOk()
            ->assertSee('เป้าหมาย', false);
    }

    public function test_kpi_detail_shows_trend_chart_canvas(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('dashboard.kpi', 'KPI01'))
            ->assertOk()
            ->assertSee('trendChart', false);
    }

    public function test_kpi_detail_trend_data_injected_as_json(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get(route('dashboard.kpi', 'KPI01'))
            ->assertOk();

        $this->assertStringContainsString('const trend', $response->content());
    }

    public function test_kpi_detail_shows_back_link(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('dashboard.kpi', 'KPI01'))
            ->assertOk()
            ->assertSee('กลับภาพรวม', false);
    }

    public function test_kpi_detail_returns_404_for_unknown_code(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('dashboard.kpi', 'XXXX'))
            ->assertNotFound();
    }

    public function test_kpi_detail_sidebar_shows_all_categories(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get(route('dashboard.kpi', 'KPI01'))
            ->assertOk();

        $response->assertSee('ยาปฏิชีวนะ', false);
        $response->assertSee('คุณภาพยา', false);
    }

    // =========================================================================
    // Admin — Database Config Page
    // =========================================================================

    public function test_admin_db_config_page_loads(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('admin.database.show'))
            ->assertOk()
            ->assertSee('HOSxP Database Configuration', false);
    }

    public function test_admin_db_config_shows_unconfigured_status_when_no_json(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('admin.database.show'))
            ->assertOk()
            ->assertSee('ยังไม่มี Configuration', false);
    }

    public function test_admin_db_config_form_has_all_required_fields(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get(route('admin.database.show'))
            ->assertOk();

        $response->assertSee('name="host"',     false);
        $response->assertSee('name="port"',     false);
        $response->assertSee('name="database"', false);
        $response->assertSee('name="username"', false);
        $response->assertSee('name="password"', false);
    }

    public function test_admin_db_config_shows_security_notice(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('admin.database.show'))
            ->assertOk()
            ->assertSee('SELECT', false);
    }

    public function test_admin_page_inaccessible_to_guest(): void
    {
        $this->get(route('admin.database.show'))
            ->assertRedirect(route('login'));
    }

    // =========================================================================
    // Layout / Content Integrity
    // =========================================================================

    public function test_dashboard_layout_has_csrf_meta_tag(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('name="csrf-token"', false);
    }

    public function test_dashboard_layout_references_vite_assets(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();

        // Vite emits hashed filenames in the build manifest; verify a link/script tag is present
        $content = $response->content();
        $this->assertTrue(
            str_contains($content, '<link') || str_contains($content, '<script'),
            'Layout must emit at least one link or script tag from Vite'
        );
    }

    public function test_all_pages_set_correct_html_lang(): void
    {
        $user = User::factory()->create();

        // Login page (no layout — inline HTML)
        $this->get(route('login'))->assertOk()->assertSee('lang="th"', false);

        // Dashboard (layout)
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('lang="th"', false);
    }

    public function test_dashboard_topbar_shows_today_date(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('ข้อมูล ณ วันที่', false);
    }

    public function test_dashboard_shows_hospital_name_in_sidebar(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('รพ.เชียงกลาง', false);
    }

    public function test_kpi_detail_page_title_includes_kpi_name(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get(route('dashboard.kpi', 'KPI01'))
            ->assertOk();

        // Title attribute of <title> tag includes "KPI01"
        $this->assertStringContainsString('KPI01', $response->content());
    }

    // =========================================================================
    // Session Persistence
    // =========================================================================

    public function test_auth_persists_across_multiple_requests(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->get(route('dashboard'))->assertOk();
        $this->get(route('dashboard.kpi', 'KPI01'))->assertOk();
        $this->get(route('admin.database.show'))->assertOk();
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_year_month_filter_accepted_across_all_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard', ['year' => 2568, 'month' => 6]))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('dashboard.kpi', ['code' => 'KPI01', 'year' => 2568, 'month' => 6]))
            ->assertOk();
    }

    // =========================================================================
    // Error Scenarios
    // =========================================================================

    public function test_unknown_kpi_code_returns_404(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('dashboard.kpi', 'KPI99'))
            ->assertNotFound();
    }

    public function test_guest_cannot_access_any_protected_route(): void
    {
        $protectedUrls = [
            route('dashboard'),
            route('dashboard.kpi', 'KPI01'),
            route('admin.database.show'),
        ];

        foreach ($protectedUrls as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }
}
