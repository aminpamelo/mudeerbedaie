<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Creates the application.
     */
    public function createApplication()
    {
        // Force testing environment BEFORE creating application
        putenv('APP_ENV=testing');
        $_ENV['APP_ENV'] = 'testing';
        $_SERVER['APP_ENV'] = 'testing';

        $app = parent::createApplication();

        // CRITICAL: Force test database configuration BEFORE any database operations
        // This overrides any .env settings to ensure we NEVER touch production database
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('database.default', 'sqlite');

        return $app;
    }

    /**
     * Boot the testing environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // CRITICAL: Ensure we're NEVER using the production database in tests
        $this->ensureTestingDatabase();
    }

    /**
     * Ensure tests are using the correct testing database.
     * This prevents accidental data loss in production/development databases.
     */
    protected function ensureTestingDatabase(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        // For SQLite, ensure we're using :memory: for tests
        if ($connection === 'sqlite' && $database !== ':memory:') {
            throw new \RuntimeException(
                "CRITICAL SAFETY ERROR: Tests must use ':memory:' database, not '{$database}'. ".
                'This configuration should have been overridden in TestCase::createApplication(). '.
                'Your production/development database is at risk!'
            );
        }

        // For other databases, ensure we're in testing environment
        if (! app()->environment('testing')) {
            throw new \RuntimeException(
                "CRITICAL SAFETY ERROR: Tests must run in 'testing' environment, currently in '".app()->environment()."'. ".
                'This prevents accidental data loss in production databases.'
            );
        }
    }
}
