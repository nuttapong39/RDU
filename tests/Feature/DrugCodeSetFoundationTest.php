<?php

namespace Tests\Feature;

use App\Models\DrugCodeSet;
use App\Models\DrugCodeSetEditor;
use App\Models\User;
use App\Services\DrugCodeSetService;
use App\Support\DrugCodeSetRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/** Phase 1 of docs/plans/drug-code-sets-and-facility-profile.md: registry, storage reads, permission. */
class DrugCodeSetFoundationTest extends TestCase
{
    use RefreshDatabase;

    // These tests start from the empty sets a new hospital has.
    protected bool $seedDrugCodeSets = false;

    private function admin(): User
    {
        return $this->makeUser('nuttapong');
    }

    private function member(string $username = 'pharmacist1'): User
    {
        return $this->makeUser($username);
    }

    // Not the factory: its Faker userName() fails without the intl extension (known baseline failure).
    private function makeUser(string $username): User
    {
        return User::firstOrCreate(['username' => $username], [
            'name' => "User {$username}", 'email' => "{$username}@example.test", 'password' => 'password',
        ]);
    }

    private function seedSet(string $key, array $items): void
    {
        DrugCodeSet::create(['key' => $key, 'version' => 1]);
        foreach ($items as $code => $attribute) {
            DrugCodeSet::find($key)->items()->create(['code' => (string) $code, 'attribute' => $attribute]);
        }
    }

    // ---- registry ----

    public function test_registry_maps_every_kpi_to_the_agreed_sets(): void
    {
        $this->assertSame(['antibiotic'], DrugCodeSetRegistry::forKpi('KPI01'));
        $this->assertSame(['antibiotic_fresh_wound'], DrugCodeSetRegistry::forKpi('KPI02'));
        $this->assertSame(['antibiotic'], DrugCodeSetRegistry::forKpi('KPI03'));
        $this->assertSame(['antibiotic'], DrugCodeSetRegistry::forKpi('KPI04'));
        $this->assertSame(['antibiotic'], DrugCodeSetRegistry::forKpi('KPI05'));
        $this->assertSame(['nsaid'], DrugCodeSetRegistry::forKpi('KPI07'));
        $this->assertSame(['ics'], DrugCodeSetRegistry::forKpi('KPI08'));
        $this->assertSame(['nsaid'], DrugCodeSetRegistry::forKpi('KPI09'));
        $this->assertSame(['metformin', 'glucose_lowering', 'egfr_lab'], DrugCodeSetRegistry::forKpi('KPI10'));
        $this->assertSame(['non_sedating_antihistamine'], DrugCodeSetRegistry::forKpi('KPI11'));
        $this->assertSame(['ras_blockade'], DrugCodeSetRegistry::forKpi('KPI12'));
        $this->assertSame(['long_acting_bzd'], DrugCodeSetRegistry::forKpi('KPI13'));
        $this->assertSame([], DrugCodeSetRegistry::forKpi('KPI06'));
    }

    public function test_registry_has_ten_sets_and_the_attribute_rules(): void
    {
        $this->assertSame(
            ['antibiotic', 'antibiotic_fresh_wound', 'nsaid', 'ics', 'metformin', 'glucose_lowering', 'egfr_lab',
             'non_sedating_antihistamine', 'ras_blockade', 'long_acting_bzd'],
            array_keys(DrugCodeSetRegistry::all())
        );
        $this->assertSame('drug', DrugCodeSetRegistry::get('antibiotic')['kind']);
        $this->assertSame('ingredient', DrugCodeSetRegistry::get('ras_blockade')['attribute']);
        $this->assertSame('form', DrugCodeSetRegistry::get('non_sedating_antihistamine')['attribute']);
        $this->assertSame('form', DrugCodeSetRegistry::get('long_acting_bzd')['attribute']);
        $this->assertSame(1, DrugCodeSetRegistry::get('egfr_lab')['max']);
        $this->assertSame('lab', DrugCodeSetRegistry::get('egfr_lab')['kind']);
        $this->assertNull(DrugCodeSetRegistry::get('antibiotic')['attribute']);
    }

    public function test_unknown_set_key_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(DrugCodeSetService::class)->items('no_such_set');
    }

    // ---- service ----

    public function test_a_new_install_has_every_set_empty_and_no_kpi_configured(): void
    {
        $service = app(DrugCodeSetService::class);

        foreach (array_keys(DrugCodeSetRegistry::all()) as $key) {
            $this->assertFalse($service->isConfigured($key), $key);
            $this->assertSame(0, $service->version($key), $key);
        }
        foreach (DrugCodeSetRegistry::kpiCodes() as $kpi) {
            $this->assertFalse($service->isKpiConfigured($kpi), $kpi);
        }
    }

    public function test_items_and_codes_come_back_with_their_attributes(): void
    {
        $this->seedSet('ras_blockade', ['1000001' => 'Enalapril', '1000002' => 'Losartan']);
        $service = app(DrugCodeSetService::class);

        $this->assertSame(['1000001' => 'Enalapril', '1000002' => 'Losartan'], $service->items('ras_blockade'));
        $this->assertSame(['1000001', '1000002'], $service->codes('ras_blockade'));
        $this->assertTrue($service->isKpiConfigured('KPI12'));
    }

    public function test_a_set_row_with_no_items_is_not_configured(): void
    {
        $this->seedSet('nsaid', []);

        $this->assertFalse(app(DrugCodeSetService::class)->isConfigured('nsaid'));
    }

    public function test_kpi10_needs_all_three_sets(): void
    {
        $service = app(DrugCodeSetService::class);

        $this->seedSet('metformin', ['1000184' => null]);
        $this->seedSet('glucose_lowering', ['1000184' => null, '1000200' => null]);
        $service->flush();
        $this->assertFalse($service->isKpiConfigured('KPI10'));
        $this->assertSame(['egfr_lab'], $service->missingSets('KPI10'));

        $this->seedSet('egfr_lab', ['200' => null]);
        $service->flush();
        $this->assertTrue($service->isKpiConfigured('KPI10'));
        $this->assertSame([], $service->missingSets('KPI10'));
    }

    public function test_a_shared_set_configures_every_kpi_that_uses_it(): void
    {
        $this->seedSet('antibiotic', ['1000010' => null]);
        $service = app(DrugCodeSetService::class);

        foreach (['KPI01', 'KPI03', 'KPI04', 'KPI05'] as $kpi) {
            $this->assertTrue($service->isKpiConfigured($kpi), $kpi);
        }
        $this->assertFalse($service->isKpiConfigured('KPI02'));
    }

    public function test_changed_at_is_null_until_a_set_is_edited_on_the_settings_page(): void
    {
        $service = app(DrugCodeSetService::class);
        $this->assertNull($service->changedAt('ics'));

        DrugCodeSet::create(['key' => 'ics', 'version' => 1]);               // as the import leaves it
        $this->assertNull($service->changedAt('ics'));

        DrugCodeSet::find('ics')->update(['changed_at' => '2026-11-10 09:00:00']);
        $this->assertSame('2026-11-10 09:00:00', $service->changedAt('ics')->format('Y-m-d H:i:s'));
    }

    public function test_version_reflects_the_stored_row(): void
    {
        DrugCodeSet::create(['key' => 'ics', 'version' => 4]);

        $this->assertSame(4, app(DrugCodeSetService::class)->version('ics'));
    }

    public function test_an_item_code_can_appear_once_per_set_but_in_many_sets(): void
    {
        $this->seedSet('antibiotic', ['1483852' => null]);
        $this->seedSet('ics', ['1483852' => null]);   // Budesonide MDI is in both (ADR 0003 note)

        $this->assertTrue(app(DrugCodeSetService::class)->isConfigured('ics'));

        $this->expectException(\Illuminate\Database\QueryException::class);
        DrugCodeSet::find('ics')->items()->create(['code' => '1483852']);
    }

    // ---- permission ----

    public function test_admin_can_edit_drug_code_sets_without_a_grant(): void
    {
        $this->assertTrue(Gate::forUser($this->admin())->allows('edit-drug-code-sets'));
    }

    public function test_an_ordinary_user_cannot_edit_drug_code_sets(): void
    {
        $this->assertFalse(Gate::forUser($this->member())->allows('edit-drug-code-sets'));
    }

    public function test_a_granted_user_can_edit_and_a_revoked_user_cannot(): void
    {
        $user = $this->member();
        $row  = DrugCodeSetEditor::create(['user_id' => $user->id]);
        $this->assertTrue(Gate::forUser($user)->allows('edit-drug-code-sets'));

        $row->delete();
        $this->assertFalse(Gate::forUser($user)->allows('edit-drug-code-sets'));
    }

    public function test_a_granted_user_is_still_not_a_settings_admin(): void
    {
        $user = $this->member();
        DrugCodeSetEditor::create(['user_id' => $user->id]);

        $this->assertFalse(Gate::forUser($user)->allows('manage-settings'));
        $this->actingAs($user)->get(route('admin.database.show'))->assertForbidden();
    }

    // ---- grant / revoke page ----

    public function test_the_editor_page_is_admin_only(): void
    {
        $user = $this->member();

        $this->actingAs($user)->get(route('admin.drug-code-editors.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.drug-code-editors.store'), ['user_id' => $user->id])->assertForbidden();
        $this->assertDatabaseCount('drug_code_set_editors', 0);
    }

    public function test_a_granted_editor_cannot_open_the_editor_page(): void
    {
        $user = $this->member();
        DrugCodeSetEditor::create(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('admin.drug-code-editors.index'))->assertForbidden();
    }

    public function test_admin_sees_users_and_the_sidebar_link(): void
    {
        $this->member('pharmacist1');

        $this->actingAs($this->admin())->get(route('admin.drug-code-editors.index'))
            ->assertOk()->assertSee('pharmacist1')->assertSee('ให้สิทธิ์');

        $this->actingAs($this->admin())->get(route('dashboard'))->assertOk()->assertSee('ผู้ตั้งค่ารหัสยา');
        $this->actingAs($this->member('other'))->get(route('dashboard'))->assertOk()->assertDontSee('ผู้ตั้งค่ารหัสยา');
    }

    public function test_admin_grants_a_permission_and_it_is_audited(): void
    {
        $admin = $this->admin();
        $user  = $this->member();

        $this->actingAs($admin)->post(route('admin.drug-code-editors.store'), ['user_id' => $user->id])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('drug_code_set_editors', ['user_id' => $user->id, 'granted_by' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'drug_code_editor.grant', 'subject' => "user:{$user->id}", 'user_id' => $admin->id]);
    }

    public function test_granting_twice_keeps_one_row_and_one_audit_entry(): void
    {
        $user = $this->member();
        $this->actingAs($this->admin());

        $this->post(route('admin.drug-code-editors.store'), ['user_id' => $user->id]);
        $this->post(route('admin.drug-code-editors.store'), ['user_id' => $user->id]);

        $this->assertDatabaseCount('drug_code_set_editors', 1);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_admins_cannot_be_granted_a_redundant_row(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.drug-code-editors.store'), ['user_id' => $admin->id])
            ->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseCount('drug_code_set_editors', 0);
    }

    public function test_granting_requires_an_existing_user(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.drug-code-editors.store'), ['user_id' => 99999])
            ->assertSessionHasErrors('user_id');

        $this->actingAs($this->admin())
            ->post(route('admin.drug-code-editors.store'), [])
            ->assertSessionHasErrors('user_id');
    }

    public function test_admin_revokes_a_permission_and_it_is_audited(): void
    {
        $user = $this->member();
        $row  = DrugCodeSetEditor::create(['user_id' => $user->id]);

        $this->actingAs($this->admin())->delete(route('admin.drug-code-editors.destroy', $row))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseMissing('drug_code_set_editors', ['user_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'drug_code_editor.revoke', 'subject' => "user:{$user->id}"]);
    }

    public function test_deleting_a_user_removes_their_grant(): void
    {
        $user = $this->member();
        DrugCodeSetEditor::create(['user_id' => $user->id]);

        $user->delete();

        $this->assertDatabaseCount('drug_code_set_editors', 0);
    }
}
