<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DatabaseConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class DatabaseConfigTest extends TestCase
{
    use RefreshDatabase;

    private string $configPath;
    private DatabaseConfigService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service    = new DatabaseConfigService();
        $this->configPath = storage_path('app/private/config/rdu_database.json');

        if (file_exists($this->configPath)) {
            unlink($this->configPath);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->configPath)) {
            unlink($this->configPath);
        }
        $backup = $this->configPath . '.bak';
        if (file_exists($backup)) {
            unlink($backup);
        }
        parent::tearDown();
    }

    // ---- hasConfig / getSafeConfig ----

    public function test_has_config_returns_false_when_no_json(): void
    {
        $this->assertFalse($this->service->hasConfig());
    }

    public function test_get_safe_config_returns_null_when_no_json(): void
    {
        $this->assertNull($this->service->getSafeConfig());
    }

    // ---- Save ----

    public function test_save_creates_json_file(): void
    {
        $this->service->save($this->sampleConfig());
        $this->assertFileExists($this->configPath);
    }

    public function test_save_encrypts_password(): void
    {
        $this->service->save($this->sampleConfig());

        $raw = json_decode(file_get_contents($this->configPath), true);
        $this->assertNotEquals('secret123', $raw['password']);
        $this->assertEquals('secret123', Crypt::decryptString($raw['password']));
    }

    public function test_save_does_not_store_plain_password(): void
    {
        $this->service->save($this->sampleConfig());
        $this->assertStringNotContainsString('secret123', file_get_contents($this->configPath));
    }

    public function test_save_sets_readonly_flag(): void
    {
        $this->service->save($this->sampleConfig());
        $raw = json_decode(file_get_contents($this->configPath), true);
        $this->assertTrue($raw['readonly']);
    }

    public function test_save_creates_backup_when_config_exists(): void
    {
        $this->service->save($this->sampleConfig());
        $this->service->save(array_merge($this->sampleConfig(), ['host' => '10.0.0.1']));
        $this->assertFileExists($this->configPath . '.bak');
    }

    // ---- Load / getSafeConfig ----

    public function test_get_safe_config_returns_data_without_password(): void
    {
        $this->service->save($this->sampleConfig());
        $safe = $this->service->getSafeConfig();
        $this->assertEquals('192.168.1.100', $safe['host']);
        $this->assertArrayNotHasKey('password', $safe);
    }

    public function test_get_connection_config_decrypts_password(): void
    {
        $this->service->save($this->sampleConfig());
        $conn = $this->service->getConnectionConfig();
        $this->assertEquals('secret123', $conn['password']);
    }

    // ---- Keep existing password when blank ----

    public function test_save_keeps_existing_password_when_blank(): void
    {
        $this->service->save($this->sampleConfig());
        $this->service->save([
            'host'     => '10.0.0.1',
            'port'     => 5432,
            'database' => 'hosxp',
            'schema'   => 'public',
            'username' => 'rdu_readonly',
            'password' => '',
        ]);
        $conn = $this->service->getConnectionConfig();
        $this->assertEquals('secret123', $conn['password']);
        $this->assertEquals('10.0.0.1', $conn['host']);
    }

    // ---- HTTP: Validation ----

    public function test_update_route_validates_required_fields(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post(route('admin.database.update'), []);
        $response->assertSessionHasErrors(['host', 'port', 'database', 'username']);
    }

    public function test_update_route_requires_password_when_no_config(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post(route('admin.database.update'), [
            'host'     => '192.168.1.100',
            'port'     => 5432,
            'database' => 'hosxp',
            'schema'   => 'public',
            'username' => 'rdu_readonly',
        ]);
        $response->assertSessionHasErrors('password');
    }

    public function test_update_route_saves_config_and_redirects(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post(route('admin.database.update'), [
            'host'     => '192.168.1.100',
            'port'     => 5432,
            'database' => 'hosxp',
            'schema'   => 'public',
            'username' => 'rdu_readonly',
            'password' => 'test_password',
        ]);
        $response->assertRedirect(route('admin.database.show'));
        $this->assertFileExists($this->configPath);
    }

    public function test_show_route_returns_ok(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('admin.database.show'));
        $response->assertOk();
        $response->assertViewIs('admin.database.show');
    }

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get(route('admin.database.show'));
        $response->assertRedirect(route('login'));
    }

    // ---- HTTP: Test connection endpoint ----

    public function test_test_route_validates_required_fields(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson(route('admin.database.test'), []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['host', 'port', 'database', 'username']);
    }

    public function test_test_route_returns_failure_for_unreachable_host(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson(route('admin.database.test'), [
            'host'     => '10.255.255.1',
            'port'     => 5432,
            'database' => 'hosxp',
            'schema'   => 'public',
            'username' => 'rdu_readonly',
            'password' => 'wrong',
        ]);
        $response->assertStatus(422);
        $response->assertJsonFragment(['success' => false]);
    }

    // ---- Audit Log ----

    public function test_update_writes_audit_log(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('admin.database.update'), [
            'host'     => '192.168.1.100',
            'port'     => 5432,
            'database' => 'hosxp',
            'schema'   => 'public',
            'username' => 'rdu_readonly',
            'password' => 'test_password',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action'  => 'db_config.update',
            'subject' => 'HOSxP Database Configuration',
        ]);
    }

    public function test_audit_log_does_not_contain_password(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('admin.database.update'), [
            'host'     => '192.168.1.100',
            'port'     => 5432,
            'database' => 'hosxp',
            'schema'   => 'public',
            'username' => 'rdu_readonly',
            'password' => 'super_secret_pw',
        ]);
        $log      = \DB::table('audit_logs')->where('action', 'db_config.update')->first();
        $newValue = json_decode($log->new_value, true);
        $this->assertArrayNotHasKey('password', $newValue);
        $this->assertStringNotContainsString('super_secret_pw', $log->new_value ?? '');
    }

    // ---- Helper ----

    private function sampleConfig(): array
    {
        return [
            'host'     => '192.168.1.100',
            'port'     => 5432,
            'database' => 'hosxp',
            'schema'   => 'public',
            'username' => 'rdu_readonly',
            'password' => 'secret123',
        ];
    }
}
