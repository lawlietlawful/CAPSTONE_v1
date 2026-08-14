<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Hard safety rail. RefreshDatabase runs `migrate:fresh`, which DROPS EVERY
     * TABLE on the active connection. If the test env were ever misconfigured to
     * point at the real database, that would wipe production data.
     *
     * This override runs immediately after the application boots (config is
     * available) and BEFORE setUpTraits() triggers the migration — the only
     * point where we can abort before any table is dropped. A guard in setUp()
     * would run too late, after the DB is already refreshed.
     */
    protected function refreshApplication()
    {
        parent::refreshApplication();

        $database = $this->app['config']->get('database.connections.'
            . $this->app['config']->get('database.default') . '.database');

        if (! str_ends_with((string) $database, '_test')) {
            throw new \RuntimeException(
                "REFUSING TO RUN TESTS: the configured database is '{$database}', which is not a *_test database. "
                . 'RefreshDatabase would drop its tables. Check phpunit.xml.'
            );
        }
    }
}
