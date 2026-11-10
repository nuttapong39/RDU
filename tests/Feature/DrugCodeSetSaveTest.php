<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DrugCodeSet;
use App\Models\DrugCodeSetEditor;
use App\Models\User;
use App\Services\DrugCodeSetService;
use App\Services\RduQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase 3 of docs/plans/drug-code-sets-and-facility-profile.md: saving a set from the settings page. */
class DrugCodeSetSaveTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seedDrugCodeSets = false;

    protected function setUp(): void
    {
        parent::setUp();

        // The hospital's drug list comes from HOSxP in real life; tests use a fixed mock catalogue.
        $this->partialMock(RduQueryService::class, function ($mock) {
            $mock->shouldReceive('drugCatalog')->andReturn([
                ['icode' => '1000001', 'name' => 'ICS A', 'strength' => '', 'units' => '', 'dosageform' => 'INH',
                    'generic_name' => 'Budesonide', 'trade_name' => '', 'drugcategory' => '', 'therapeuticgroup' => '',
                    'antibiotic' => false, 'active' => true],
                ['icode' => '1000002', 'name' => 'ICS B', 'strength' => '', 'units' => '', 'dosageform' => 'INH',
                    'generic_name' => 'Fluticasone', 'trade_name' => '', 'drugcategory' => '', 'therapeuticgroup' => '',
                    'antibiotic' => false, 'active' => true],
                ['icode' => '1000003', 'name' => 'Drug C', 'strength' => '', 'units' => '', 'dosageform' => 'TAB',
                    'generic_name' => 'Enalapril', 'trade_name' => '', 'drugcategory' => '', 'therapeuticgroup' => '',
                    'antibiotic' => false, 'active' => true],
            ]);
            $mock->shouldReceive('searchLabItems')->andReturnUsing(fn (string $term) => $term === '200'
                ? [['code' => '200', 'name' => 'eGFR', 'unit' => 'mL/min']] : []);
            $mock->shouldReceive('labItemExists')->andReturnUsing(fn (string $code) => $code === '200');
        });
    }

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
        DrugCodeSetEditor::create(['user_id' => $user->id]);

        return $user;
    }

    private function save(User $as, string $set, array $payload)
    {
        return $this->actingAs($as)->putJson(route('drug-code-sets.update', ['set' => $set]), $payload);
    }

    private function item(string $code, ?string $attribute = null): array
    {
        return ['code' => $code, 'attribute' => $attribute];
    }

    // ---- permission ----

    public function test_a_user_without_the_grant_cannot_save_or_load_the_catalogue(): void
    {
        $viewer = $this->user('viewer1');

        $this->save($viewer, 'ics', ['version' => 0, 'items' => [$this->item('1000001')]])->assertForbidden();
        $this->actingAs($viewer)->getJson(route('drug-code-sets.catalog'))->assertForbidden();
        $this->actingAs($viewer)->getJson(route('drug-code-sets.lab-items', ['q' => 'egfr']))->assertForbidden();
        $this->assertSame(0, DrugCodeSet::count());
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->putJson(route('drug-code-sets.update', ['set' => 'ics']), ['version' => 0, 'items' => []])->assertUnauthorized();
    }

    public function test_an_editor_and_an_admin_can_save(): void
    {
        $this->save($this->editor(), 'ics', ['version' => 0, 'items' => [$this->item('1000001')]])->assertOk();
        $this->save($this->user('nuttapong'), 'ics', ['version' => 1, 'items' => [$this->item('1000002')]])->assertOk();
    }

    // ---- saving ----

    public function test_saving_stores_items_in_order_bumps_the_version_and_stamps_the_change(): void
    {
        $response = $this->save($this->editor(), 'ics', ['version' => 0, 'items' => [$this->item('1000002'), $this->item('1000001')]]);

        $response->assertOk()->assertJsonPath('version', 1)->assertJsonPath('count', 2);
        $sets = app(DrugCodeSetService::class);
        $this->assertSame(['1000002', '1000001'], $sets->codes('ics'));
        $this->assertSame(1, $sets->version('ics'));
        $this->assertNotNull($sets->changedAt('ics'));
    }

    public function test_saving_replaces_the_previous_contents(): void
    {
        $user = $this->editor();
        $this->save($user, 'ics', ['version' => 0, 'items' => [$this->item('1000001'), $this->item('1000002')]])->assertOk();
        $this->save($user, 'ics', ['version' => 1, 'items' => [$this->item('1000002')]])->assertOk();

        $this->assertSame(['1000002'], app(DrugCodeSetService::class)->codes('ics'));
        $this->assertSame(2, app(DrugCodeSetService::class)->version('ics'));
    }

    public function test_a_stale_version_is_rejected_with_409_and_changes_nothing(): void
    {
        $user = $this->editor();
        $this->save($user, 'ics', ['version' => 0, 'items' => [$this->item('1000001')]])->assertOk();

        $this->save($user, 'ics', ['version' => 0, 'items' => [$this->item('1000002')]])
            ->assertStatus(409)->assertJsonPath('conflict', true);

        $this->assertSame(['1000001'], app(DrugCodeSetService::class)->codes('ics'));
        $this->assertSame(1, app(DrugCodeSetService::class)->version('ics'));
    }

    public function test_saving_writes_an_audit_log_with_the_codes_added_and_removed(): void
    {
        $user = $this->editor();
        $this->save($user, 'ics', ['version' => 0, 'items' => [$this->item('1000001')]])->assertOk();
        $this->save($user, 'ics', ['version' => 1, 'items' => [$this->item('1000002')]])->assertOk();

        $log = AuditLog::where('action', 'drug_code_set.update')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('drug_code_set:ics', $log->subject);
        $this->assertSame(['1000002'], $log->new_value['added']);
        $this->assertSame(['1000001'], $log->new_value['removed']);
    }

    // ---- validation ----

    public function test_an_empty_set_needs_confirmation(): void
    {
        $user = $this->editor();
        $this->save($user, 'ics', ['version' => 0, 'items' => [$this->item('1000001')]])->assertOk();

        $this->save($user, 'ics', ['version' => 1, 'items' => []])
            ->assertStatus(422)->assertJsonPath('needs_confirmation', true);
        $this->assertSame(['1000001'], app(DrugCodeSetService::class)->codes('ics'));

        $this->save($user, 'ics', ['version' => 1, 'items' => [], 'confirm_empty' => true])->assertOk();
        $this->assertSame([], app(DrugCodeSetService::class)->codes('ics'));
    }

    public function test_a_code_that_is_not_in_the_hospital_catalogue_is_rejected(): void
    {
        $this->save($this->editor(), 'ics', ['version' => 0, 'items' => [$this->item('9999999')]])->assertStatus(422);
        $this->assertSame(0, DrugCodeSet::count());
    }

    public function test_a_code_with_unsafe_characters_is_rejected(): void
    {
        $this->save($this->editor(), 'ics', ['version' => 0, 'items' => [$this->item("1'); DROP TABLE x;--")]])
            ->assertStatus(422)->assertJsonValidationErrors('items.0.code');
    }

    public function test_the_same_code_twice_is_rejected(): void
    {
        $this->save($this->editor(), 'ics', ['version' => 0, 'items' => [$this->item('1000001'), $this->item('1000001')]])
            ->assertStatus(422);
    }

    public function test_an_unknown_set_is_a_404(): void
    {
        $this->save($this->editor(), 'not_a_set', ['version' => 0, 'items' => []])->assertNotFound();
    }

    public function test_a_set_that_needs_an_attribute_must_have_it_on_every_item(): void
    {
        $user = $this->editor();

        $this->save($user, 'ras_blockade', ['version' => 0, 'items' => [$this->item('1000003')]])
            ->assertStatus(422)->assertJsonValidationErrors('items.0.attribute');

        $this->save($user, 'ras_blockade', ['version' => 0, 'items' => [$this->item('1000003', 'enalapril')]])->assertOk();
        $this->assertSame(['1000003' => 'enalapril'], app(DrugCodeSetService::class)->items('ras_blockade'));
    }

    public function test_a_set_without_an_attribute_ignores_any_attribute_sent(): void
    {
        $this->save($this->editor(), 'ics', ['version' => 0, 'items' => [$this->item('1000001', 'junk')]])->assertOk();

        $this->assertSame(['1000001' => null], app(DrugCodeSetService::class)->items('ics'));
    }

    public function test_the_egfr_lab_set_takes_one_item_that_exists_in_the_lab_catalogue(): void
    {
        $user = $this->editor();

        $this->save($user, 'egfr_lab', ['version' => 0, 'items' => [$this->item('777')]])->assertStatus(422);
        $this->save($user, 'egfr_lab', ['version' => 0, 'items' => [$this->item('200'), $this->item('201')]])->assertStatus(422);
        $this->save($user, 'egfr_lab', ['version' => 0, 'items' => [$this->item('200')]])->assertOk();
    }

    public function test_saving_a_drug_that_another_set_holds_warns_but_saves(): void
    {
        $user = $this->editor();
        $this->save($user, 'nsaid', ['version' => 0, 'items' => [$this->item('1000001')]])->assertOk();

        $this->save($user, 'ics', ['version' => 0, 'items' => [$this->item('1000001')]])
            ->assertOk()->assertJsonPath('overlaps.1000001.0', 'ยากลุ่ม NSAIDs');
    }

    // ---- effect on the KPIs ----

    public function test_saving_a_set_makes_a_kpi_configured_and_emptying_it_makes_it_unconfigured_again(): void
    {
        $user = $this->editor();
        $sets = app(DrugCodeSetService::class);
        $this->assertFalse($sets->isKpiConfigured('KPI08'));

        $this->save($user, 'ics', ['version' => 0, 'items' => [$this->item('1000001')]])->assertOk();
        $this->assertTrue($sets->isKpiConfigured('KPI08'));
        $this->assertSame('1', $sets->cacheVersion('KPI08'));

        $this->save($user, 'ics', ['version' => 1, 'items' => [], 'confirm_empty' => true])->assertOk();
        $this->assertFalse($sets->isKpiConfigured('KPI08'));
        $this->assertSame('2', $sets->cacheVersion('KPI08'));
    }

    // ---- reference data endpoints ----

    public function test_the_catalogue_endpoint_returns_only_the_agreed_columns(): void
    {
        $drug = $this->actingAs($this->editor())->getJson(route('drug-code-sets.catalog'))
            ->assertOk()->json('drugs.0');

        $this->assertEqualsCanonicalizing(
            ['icode', 'name', 'strength', 'units', 'dosageform', 'generic_name', 'trade_name', 'drugcategory', 'therapeuticgroup', 'antibiotic', 'active'],
            array_keys($drug)
        );
    }

    public function test_the_lab_search_returns_matches(): void
    {
        $this->actingAs($this->editor())->getJson(route('drug-code-sets.lab-items', ['q' => '200']))
            ->assertOk()->assertJsonPath('items.0.code', '200');
    }

    // ---- the page ----

    public function test_an_editor_gets_the_editor_config_and_a_viewer_does_not(): void
    {
        $this->actingAs($this->editor())->get(route('drug-code-sets.index'))
            ->assertOk()->assertSee('data-drug-code-editor', false);

        $this->actingAs($this->user('viewer1'))->get(route('drug-code-sets.index'))
            ->assertOk()->assertDontSee('data-drug-code-editor', false)->assertSee('ดูชุดรหัสยาได้อย่างเดียว');
    }
}
