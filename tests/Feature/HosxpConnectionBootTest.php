<?php

namespace Tests\Feature;

use App\Exceptions\ReadonlyDatabaseException;
use App\Providers\AppServiceProvider;
use App\Services\DatabaseConfigService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The HOSxP connection is configured from rdu_database.json on first use, never at boot:
 *  - `php artisan config:cache` dumps the booted config to bootstrap/cache/config.php, so a password
 *    put into config at boot would be written there in plain text;
 *  - artisan commands that never touch HOSxP (down, migrate, …) must not need to read the file
 *    (on the server it belongs to the web user and the deploy user cannot read it).
 * The file lives in the per-test temp storage (see TestCase), never the real one.
 */
class HosxpConnectionBootTest extends TestCase
{
    private const PLAIN_PASSWORD = 'hosxp-secret-for-test';

    protected function setUp(): void
    {
        parent::setUp();

        app(DatabaseConfigService::class)->save([
            'host' => '10.0.0.99', 'port' => 5432, 'database' => 'hosxp_test',
            'username' => 'rdu_readonly', 'password' => self::PLAIN_PASSWORD,
        ]);

        // Boot the provider again as a non-testing app would, with a config file present.
        $this->app['env'] = 'production';
        (new AppServiceProvider($this->app))->boot();
        DB::purge('rdu');
    }

    protected function tearDown(): void
    {
        $this->app['env'] = 'testing';
        foreach (['rdu_database.json', 'rdu_database.json.bak'] as $file) {
            $path = storage_path('app/private/config/'.$file);
            if (str_starts_with($path, sys_get_temp_dir()) && is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    public function test_booting_does_not_put_the_hosxp_password_into_config(): void
    {
        $this->assertNotSame(self::PLAIN_PASSWORD, config('database.connections.rdu.password'));
        $this->assertStringNotContainsString(self::PLAIN_PASSWORD, var_export(config()->all(), true));
    }

    public function test_the_hosxp_connection_still_uses_the_saved_config_when_first_used(): void
    {
        $connection = DB::connection('rdu');

        $this->assertSame('10.0.0.99', $connection->getConfig('host'));
        $this->assertSame('hosxp_test', $connection->getConfig('database'));
        $this->assertSame(self::PLAIN_PASSWORD, $connection->getConfig('password'));
    }

    public function test_the_lazily_built_hosxp_connection_still_blocks_writes(): void
    {
        $this->expectException(ReadonlyDatabaseException::class);
        DB::connection('rdu')->statement("INSERT INTO patient (hn) VALUES ('test')");
    }
}
