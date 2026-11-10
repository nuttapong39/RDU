<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\FacilityProfileService;
use App\Services\HdcComparisonService;
use App\Services\HdcDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Phase 5 of docs/plans/drug-code-sets-and-facility-profile.md: the hospital's own name, province, hospcode and logo. */
class FacilityProfileTest extends TestCase
{
    use RefreshDatabase;

    // Start like a new hospital: nothing set (the default TestCase setup imports Chiang Klang).
    protected bool $seedDrugCodeSets = false;

    /** A real 1×1 PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        app(FacilityProfileService::class)->flush();
    }

    // Not the factory: its Faker userName() fails without the intl extension (known baseline failure).
    private function user(string $username): User
    {
        return User::firstOrCreate(['username' => $username], [
            'name' => "User {$username}", 'email' => "{$username}@example.test", 'password' => 'password',
        ]);
    }

    private function admin(): User
    {
        return $this->user('nuttapong');
    }

    private function valid(array $override = []): array
    {
        return array_merge([
            'name' => 'โรงพยาบาลตัวอย่าง', 'short_name' => 'รพ.ตัวอย่าง', 'province_code' => '55', 'hospcode' => '12345',
        ], $override);
    }

    private function png(string $name = 'logo.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(self::PNG));
    }

    private function facility(): FacilityProfileService
    {
        app(FacilityProfileService::class)->flush();

        return app(FacilityProfileService::class);
    }

    // ---- permission ----

    public function test_only_an_admin_can_open_or_save_the_page(): void
    {
        $member = $this->user('pharmacist1');

        $this->actingAs($member)->get(route('admin.facility.show'))->assertForbidden();
        $this->actingAs($member)->post(route('admin.facility.update'), $this->valid())->assertForbidden();
        $this->assertFalse($this->facility()->isConfigured());

        $this->actingAs($this->admin())->get(route('admin.facility.show'))->assertOk()->assertSee('ข้อมูลสถานบริการ');
    }

    // ---- saving ----

    public function test_saving_stores_the_profile_and_writes_an_audit_log(): void
    {
        $this->actingAs($this->admin())->post(route('admin.facility.update'), $this->valid())
            ->assertRedirect()->assertSessionHas('success');

        $p = $this->facility()->profile();
        $this->assertTrue($p['configured']);
        $this->assertSame('โรงพยาบาลตัวอย่าง', $p['name']);
        $this->assertSame('รพ.ตัวอย่าง', $p['shortName']);
        $this->assertSame('น่าน', $p['provinceName']);
        $this->assertSame('12345', $p['hospcode']);
        $this->assertSame('โรงพยาบาลตัวอย่าง จังหวัดน่าน', $p['title']);
        $this->assertSame('รพ.ตัวอย่าง จ.น่าน', $p['shortTitle']);
        $this->assertNotNull(AuditLog::where('action', 'facility.update')->first());
    }

    public function test_bangkok_is_not_called_a_changwat(): void
    {
        $this->actingAs($this->admin())->post(route('admin.facility.update'), $this->valid(['province_code' => '10']));

        $this->assertSame('โรงพยาบาลตัวอย่าง กรุงเทพมหานคร', $this->facility()->profile()['title']);
    }

    public function test_the_province_list_has_77_entries_with_two_digit_codes(): void
    {
        $provinces = FacilityProfileService::provinces();

        $this->assertCount(77, $provinces);
        $this->assertSame('น่าน', $provinces['55']);
        foreach (array_keys($provinces) as $code) {
            $this->assertMatchesRegularExpression('/^\d{2}$/', (string) $code);
        }
    }

    // ---- validation ----

    /** @dataProvider invalidInputs */
    public function test_invalid_input_is_rejected(array $override, string $field): void
    {
        $this->actingAs($this->admin())->post(route('admin.facility.update'), $this->valid($override))
            ->assertSessionHasErrors($field);

        $this->assertFalse($this->facility()->isConfigured());
    }

    public static function invalidInputs(): array
    {
        return [
            'no name'            => [['name' => ''], 'name'],
            'no short name'      => [['short_name' => ''], 'short_name'],
            'unknown province'   => [['province_code' => '99'], 'province_code'],
            'hospcode too short' => [['hospcode' => '1234'], 'hospcode'],
            'hospcode letters'   => [['hospcode' => '12a45'], 'hospcode'],
        ];
    }

    public function test_the_hospcode_may_be_left_empty(): void
    {
        $this->actingAs($this->admin())->post(route('admin.facility.update'), $this->valid(['hospcode' => '']))
            ->assertSessionHasNoErrors();

        $this->assertFalse($this->facility()->profile()['hasHospcode']);
    }

    // ---- logo ----

    public function test_a_png_logo_is_stored_under_a_random_name_and_served_without_signing_in(): void
    {
        $this->actingAs($this->admin())->post(route('admin.facility.update'), $this->valid() + ['logo' => $this->png('my logo.png')])
            ->assertSessionHasNoErrors();

        $path = $this->facility()->logoPath();
        $this->assertNotNull($path);
        $this->assertMatchesRegularExpression('#^facility/[A-Za-z0-9]{40}\.png$#', $path);
        Storage::disk('local')->assertExists($path);

        auth()->logout();
        $this->get(route('facility.logo'))->assertOk()
            ->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_there_is_no_logo_url_until_one_is_uploaded(): void
    {
        $this->get(route('facility.logo'))->assertNotFound();
    }

    public function test_an_svg_logo_is_rejected(): void
    {
        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->actingAs($this->admin())->post(route('admin.facility.update'), $this->valid() + ['logo' => $svg])
            ->assertSessionHasErrors('logo');
        $this->assertNull($this->facility()->logoPath());
    }

    public function test_a_file_named_png_that_is_not_an_image_is_rejected(): void
    {
        $fake = UploadedFile::fake()->createWithContent('logo.png', '<?php echo "not an image";');

        $this->actingAs($this->admin())->post(route('admin.facility.update'), $this->valid() + ['logo' => $fake])
            ->assertSessionHasErrors('logo');
        $this->assertNull($this->facility()->logoPath());
    }

    public function test_a_logo_over_one_megabyte_is_rejected(): void
    {
        $big = UploadedFile::fake()->image('big.png', 100, 100)->size(1500);

        $this->actingAs($this->admin())->post(route('admin.facility.update'), $this->valid() + ['logo' => $big])
            ->assertSessionHasErrors('logo');
    }

    public function test_saving_again_without_a_file_keeps_the_logo_and_a_new_one_does_not_delete_the_old(): void
    {
        $this->actingAs($this->admin())->post(route('admin.facility.update'), $this->valid() + ['logo' => $this->png()]);
        $first = $this->facility()->logoPath();

        $this->actingAs($this->admin())->post(route('admin.facility.update'), $this->valid(['name' => 'ชื่อใหม่']));
        $this->assertSame($first, $this->facility()->logoPath());

        $this->actingAs($this->admin())->post(route('admin.facility.update'), $this->valid() + ['logo' => $this->png('b.png')]);
        $second = $this->facility()->logoPath();
        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertExists($first);
        Storage::disk('local')->assertExists($second);
    }

    // ---- what the pages show ----

    public function test_without_a_facility_the_pages_name_no_hospital(): void
    {
        $login = $this->get(route('login'))->assertOk();
        $login->assertSee('RDU Dashboard');
        $login->assertDontSee('โรงพยาบาลเชียงกลาง');
        $login->assertDontSee('images/logo_ck.png', false);
    }

    public function test_an_admin_is_warned_that_the_facility_is_not_set_but_other_users_are_not(): void
    {
        $this->actingAs($this->admin())->get(route('dashboard'))->assertSee('data-facility-warning', false);
        $this->actingAs($this->user('pharmacist1'))->get(route('dashboard'))->assertDontSee('data-facility-warning', false);
    }

    public function test_once_set_the_name_shows_on_the_login_page_and_the_dashboard(): void
    {
        $this->actingAs($this->admin())->post(route('admin.facility.update'), $this->valid());
        auth()->logout();

        $this->get(route('login'))->assertSee('โรงพยาบาลตัวอย่าง จังหวัดน่าน')->assertDontSee('โรงพยาบาลเชียงกลาง');
        $this->actingAs($this->admin())->get(route('dashboard'))
            ->assertSee('รพ.ตัวอย่าง จ.น่าน')->assertDontSee('data-facility-warning', false);
    }

    // ---- HDC ----

    public function test_the_hdc_comparison_is_off_until_a_hospcode_is_set(): void
    {
        $this->actingAs($this->admin())->post(route('admin.facility.update'), $this->valid(['hospcode' => '']));
        $this->assertFalse(app(HdcDataService::class)->isLinked('KPI01'));

        $this->actingAs($this->admin())->post(route('admin.facility.update'), $this->valid(['hospcode' => '12345']));
        $this->assertTrue(app(HdcDataService::class)->isLinked('KPI01'));
        $this->assertFalse(app(HdcDataService::class)->isLinked('KPI07'), 'a KPI with no HDC table stays unlinked');
    }

    public function test_a_district_name_is_shown_only_where_the_data_has_it(): void
    {
        $label = fn (string $hospcode, string $areacode) => (fn () => $this->label($hospcode, $areacode))
            ->call(app(HdcComparisonService::class));

        $this->assertSame('อ.เชียงกลาง (11179)', $label('11179', '550901'));
        $this->assertSame('99999', $label('99999', '100101'), 'a province without district data falls back to the hospcode');
    }

    // ---- the one-time import ----

    public function test_the_import_sets_chiang_klang_once_and_never_overwrites_an_edit(): void
    {
        Artisan::call('rdu:import-drug-code-sets');
        $p = $this->facility()->profile();
        $this->assertSame('โรงพยาบาลเชียงกลาง', $p['name']);
        $this->assertSame('11179', $p['hospcode']);
        $this->assertSame('55', $p['provinceCode']);
        $this->assertNotNull($this->facility()->logoPath(), 'the old logo file is carried over');

        $this->actingAs($this->admin())->post(route('admin.facility.update'), $this->valid(['name' => 'ชื่อที่แก้เอง']));
        Artisan::call('rdu:import-drug-code-sets');

        $this->assertSame('ชื่อที่แก้เอง', $this->facility()->profile()['name']);
    }

    public function test_after_the_import_the_login_page_looks_as_before(): void
    {
        Artisan::call('rdu:import-drug-code-sets');

        $this->get(route('login'))->assertSee('โรงพยาบาลเชียงกลาง จังหวัดน่าน')->assertSee('สำหรับเจ้าหน้าที่โรงพยาบาลเชียงกลาง')
            ->assertSee('พัฒนาโดย', false);
    }
}
