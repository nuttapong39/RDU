<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Every test runs against a throw-away storage directory. Tests such as DatabaseConfigTest create
     * and delete storage/app/private/config/rdu_database.json — pointed at the real storage path they
     * deleted the live HOSxP connection config (and with it, HOSxP login) on every test run.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $storage = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rdu-test-storage';
        foreach (['app/private/config', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $dir) {
            if (! is_dir($storage.'/'.$dir)) {
                mkdir($storage.'/'.$dir, 0777, true);
            }
        }
        $app->useStoragePath($storage);

        return $app;
    }
}
