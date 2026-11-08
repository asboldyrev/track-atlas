<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Never run database-writing tests against a real music catalog.
     * A stale Laravel config cache can override phpunit.xml's :memory: settings.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        if (! $app->environment('testing')
            || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException(
                'Unsafe test environment: refusing to run. '
                .'Execute php artisan config:clear and check phpunit.xml: '
                .'APP_ENV=testing, DB_CONNECTION=sqlite, DB_DATABASE=:memory:.'
            );
        }

        return $app;
    }
}
