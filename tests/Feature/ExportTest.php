<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RduIndicatorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;
    protected string $seeder = RduIndicatorSeeder::class;

    // ---- Route resolution ----

    public function test_export_routes_are_registered(): void
    {
        $this->assertNotNull(route('kpi.export.csv',   ['code' => 'KPI01']));
        $this->assertNotNull(route('kpi.export.excel', ['code' => 'KPI01']));
        $this->assertNotNull(route('kpi.export.pdf',   ['code' => 'KPI01']));
    }

    // ---- Auth guard ----

    public function test_guest_cannot_access_csv_export(): void
    {
        $this->get(route('kpi.export.csv', ['code' => 'KPI01']))->assertRedirect(route('login'));
    }

    public function test_guest_cannot_access_excel_export(): void
    {
        $this->get(route('kpi.export.excel', ['code' => 'KPI01']))->assertRedirect(route('login'));
    }

    public function test_guest_cannot_access_pdf_export(): void
    {
        $this->get(route('kpi.export.pdf', ['code' => 'KPI01']))->assertRedirect(route('login'));
    }

    // ---- CSV export ----

    public function test_csv_export_returns_200_for_valid_code(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get(route('kpi.export.csv', ['code' => 'KPI01', 'year' => 2568, 'month' => 1]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_csv_export_filename_contains_code_and_year(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get(route('kpi.export.csv', ['code' => 'KPI01', 'year' => 2568, 'month' => 0]));

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('kpi01', strtolower($disposition));
        $this->assertStringContainsString('2568', $disposition);
        $this->assertStringContainsString('.csv', $disposition);
    }

    public function test_csv_export_returns_404_for_invalid_code(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('kpi.export.csv', ['code' => 'INVALID']))
            ->assertNotFound();
    }

    public function test_csv_body_contains_utf8_bom(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get(route('kpi.export.csv', ['code' => 'KPI01', 'year' => 2568, 'month' => 1]));

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
    }

    public function test_csv_body_contains_indicator_code(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get(route('kpi.export.csv', ['code' => 'KPI01', 'year' => 2568, 'month' => 1]));

        $content = $response->streamedContent();
        $this->assertStringContainsString('KPI01', $content);
    }

    // ---- Excel export ----

    public function test_excel_export_returns_200_for_valid_code(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get(route('kpi.export.excel', ['code' => 'KPI01', 'year' => 2568, 'month' => 1]));

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            $response->headers->get('Content-Type') ?? ''
        );
    }

    public function test_excel_filename_ends_with_xlsx(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get(route('kpi.export.excel', ['code' => 'KPI01', 'year' => 2568, 'month' => 1]));

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('.xlsx', $disposition);
    }

    public function test_excel_export_returns_404_for_invalid_code(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('kpi.export.excel', ['code' => 'INVALID']))
            ->assertNotFound();
    }

    // ---- PDF export ----

    public function test_pdf_export_returns_200_for_valid_code(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get(route('kpi.export.pdf', ['code' => 'KPI01', 'year' => 2568, 'month' => 1]));

        $response->assertOk();
        $this->assertStringContainsString('pdf', $response->headers->get('Content-Type') ?? '');
    }

    public function test_pdf_filename_ends_with_pdf(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get(route('kpi.export.pdf', ['code' => 'KPI01', 'year' => 2568, 'month' => 1]));

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('.pdf', $disposition);
    }

    public function test_pdf_export_returns_404_for_invalid_code(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('kpi.export.pdf', ['code' => 'INVALID']))
            ->assertNotFound();
    }

    // ---- KPI detail page shows export buttons ----

    public function test_kpi_detail_page_shows_export_links(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get(route('dashboard.kpi', 'KPI01'));

        $response->assertOk();
        $response->assertSee(route('kpi.export.csv',   ['code' => 'KPI01']), false);
        $response->assertSee(route('kpi.export.excel', ['code' => 'KPI01']), false);
        $response->assertSee(route('kpi.export.pdf',   ['code' => 'KPI01']), false);
    }

    // ---- Month param in filename ----

    public function test_csv_filename_includes_month_when_specified(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get(route('kpi.export.csv', ['code' => 'KPI01', 'year' => 2568, 'month' => 3]));

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('03', $disposition);
    }

    public function test_csv_filename_excludes_month_suffix_when_month_is_zero(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get(route('kpi.export.csv', ['code' => 'KPI01', 'year' => 2568, 'month' => 0]));

        $disposition = $response->headers->get('Content-Disposition');
        // Full-year export: filename = kpi01-2568.csv (no month suffix)
        $this->assertMatchesRegularExpression('/kpi01-2568\.csv/', $disposition);
    }
}
