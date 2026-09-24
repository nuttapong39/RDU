<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\FontService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FontSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['username' => 'nuttapong']);
    }

    private function member(): User
    {
        return User::factory()->create(['username' => 'someone_else']);
    }

    public function test_settings_pages_are_forbidden_for_non_admin_users(): void
    {
        $this->actingAs($this->member())->get(route('admin.database.show'))->assertForbidden();
        $this->actingAs($this->member())
            ->post(route('admin.fonts.update'), ['font_sans' => 'Sarabun', 'font_mono' => 'Fira Code'])
            ->assertForbidden();
    }

    public function test_settings_admin_sees_font_card_and_sidebar_link_is_hidden_from_others(): void
    {
        $this->actingAs($this->admin())->get(route('admin.database.show'))
            ->assertOk()->assertSee('id="fontForm"', false);

        $this->actingAs($this->member())->get(route('dashboard'))
            ->assertOk()->assertDontSee('ตั้งค่าฐานข้อมูล HOSxP');
    }

    public function test_admin_can_save_fonts_and_layouts_use_them(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.fonts.update'), ['font_sans' => 'Noto Sans Thai', 'font_mono' => 'JetBrains Mono'])
            ->assertRedirect();

        $this->assertSame('Noto Sans Thai', Setting::getValue('font_sans'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'font_config.update']);

        $this->get(route('dashboard'))->assertSee("--font-sans:'Noto Sans Thai'", false);
    }

    public function test_unknown_font_names_are_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.fonts.update'), ['font_sans' => 'Evil"; } body{display:none', 'font_mono' => 'Fira Code'])
            ->assertSessionHasErrors('font_sans');

        $this->assertNull(Setting::getValue('font_sans'));
    }

    public function test_reset_restores_default_fonts(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.fonts.update'), ['font_sans' => 'Noto Sans Thai', 'font_mono' => 'JetBrains Mono']);
        $this->post(route('admin.fonts.reset'))->assertRedirect();

        $this->assertSame(
            ['sans' => FontService::DEFAULT_SANS, 'mono' => FontService::DEFAULT_MONO],
            app(FontService::class)->current()
        );
        $this->assertSame(2, AuditLog::where('action', 'font_config.update')->count());
    }
}
