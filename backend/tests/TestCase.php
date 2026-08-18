<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUpTraits(): array
    {
        $this->assertTestDatabaseIsInMemory();

        return parent::setUpTraits();
    }

    /**
     * RefreshDatabase migrates-fresh whatever DB is configured, which would wipe
     * the real MySQL dev DB if phpunit.xml were bypassed. Refuse to run unless
     * the suite is pointed at an in-memory SQLite database.
     */
    private function assertTestDatabaseIsInMemory(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                sprintf(
                    'Refusing to run the test suite: tests would wipe DB_CONNECTION=%s (DB_DATABASE=%s). '
                    .'Point tests at sqlite :memory: (phpunit.xml already does) to run safely.',
                    $connection,
                    $database
                )
            );
        }
    }
}
