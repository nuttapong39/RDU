<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DrugCodeSetEditor;
use App\Models\RduIndicator;
use App\Models\User;
use Database\Seeders\RduIndicatorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase 4 of docs/plans/drug-code-sets-and-facility-profile.md: a hospital switches KPIs it does not use on or off. */
class KpiActiveSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;
    protected string $seeder = RduIndicatorSeeder::class;

    // Not the factory: its Faker userName() fails without the intl extension (known baseline failure).
    private function user(string $username): User
    {
        return User::firstOrCreate(['username' => $username], [
            'name' => "User {$username}", 'email' => "{$username}@example.test", 'password' => 'password',
        ]);
    }

    private function editor(): User
    {
        $user = $this->user('pharmacist1');
        DrugCodeSetEditor::firstOrCreate(['user_id' => $user->id]);

        return $user;
    }

    private function switchKpi(User $as, string $code, bool $active)
    {
        return $this->actingAs($as)->putJson(route('drug-code-sets.kpi-active', ['code' => $code]), ['active' => $active]);
    }

    public function test_only_an_editor_can_switch_a_kpi(): void
    {
        $this->switchKpi($this->user('viewer1'), 'KPI08', false)->assertForbidden();
        $this->assertTrue(RduIndicator::where('code', 'KPI08')->value('is_active'));

        $this->switchKpi($this->editor(), 'KPI08', false)->assertOk()->assertJsonPath('active', false);
        $this->switchKpi($this->user('nuttapong'), 'KPI08', true)->assertOk()->assertJsonPath('active', true);
    }

    public function test_a_switched_off_kpi_disappears_everywhere_it_was_listed(): void
    {
        $editor = $this->editor();
        $this->switchKpi($editor, 'KPI08', false)->assertOk();

        $viewer = $this->user('viewer1');
        $this->actingAs($viewer)->get(route('dashboard'))->assertOk()
            ->assertDontSee('KPI08')->assertSee('ภาพรวม 11 ตัวชี้วัด');
        $this->actingAs($viewer)->get(route('dashboard.kpi', 'KPI08'))->assertNotFound();
        $this->actingAs($viewer)->get(route('dashboard.counting-methods'))->assertDontSee('KPI08');
        $this->actingAs($viewer)->get(route('drug-code-sets.index'))->assertDontSee('KPI08');
        auth()->logout(); // the login page is for guests
        $this->get('/login')->assertOk()->assertDontSee('KPI08');
    }

    public function test_an_editor_still_sees_the_switched_off_kpi_on_the_settings_page_to_switch_it_back(): void
    {
        $editor = $this->editor();
        $this->switchKpi($editor, 'KPI08', false)->assertOk();

        $this->actingAs($editor)->get(route('drug-code-sets.index'))
            ->assertOk()->assertSee('KPI08')->assertSee('"active":false', false);
    }

    public function test_switching_back_on_restores_it(): void
    {
        $editor = $this->editor();
        $this->switchKpi($editor, 'KPI08', false)->assertOk();
        $this->switchKpi($editor, 'KPI08', true)->assertOk();

        $this->actingAs($this->user('viewer1'))->get(route('dashboard'))->assertSee('KPI08')->assertSee('ภาพรวม 12 ตัวชี้วัด');
    }

    public function test_switching_is_audited_only_when_the_state_changes(): void
    {
        $editor = $this->editor();
        $this->switchKpi($editor, 'KPI08', false)->assertOk();
        $this->switchKpi($editor, 'KPI08', false)->assertOk();

        $this->assertSame(1, AuditLog::where('action', 'kpi.active')->count());
        $log = AuditLog::where('action', 'kpi.active')->first();
        $this->assertSame('kpi:KPI08', $log->subject);
        $this->assertTrue($log->old_value['is_active']);
        $this->assertFalse($log->new_value['is_active']);
    }

    public function test_an_unknown_kpi_and_a_missing_value_are_rejected(): void
    {
        $editor = $this->editor();

        $this->switchKpi($editor, 'KPI06', false)->assertNotFound();
        $this->switchKpi($editor, 'NOPE', false)->assertNotFound();
        $this->actingAs($editor)->putJson(route('drug-code-sets.kpi-active', ['code' => 'KPI08']), [])->assertStatus(422);
    }

    public function test_a_kpi_without_a_hosxp_query_cannot_be_switched(): void
    {
        $this->withKpiAwaitingQuery('KPI08');

        $this->switchKpi($this->editor(), 'KPI08', false)->assertNotFound();
    }
}
