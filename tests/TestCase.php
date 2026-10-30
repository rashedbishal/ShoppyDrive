<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Runs before RefreshDatabase (and DatabaseMigrations/DatabaseTruncation)
     * can migrate or modify anything — see Tests\SafeTestDatabase.
     */
    protected function setUpTraits()
    {
        $connections = array_unique([
            config('database.default'),
            ...(property_exists($this, 'connectionsToTransact') ? $this->connectionsToTransact : []),
        ]);

        foreach ($connections as $connection) {
            SafeTestDatabase::assertSafe((string) $connection, (array) config("database.connections.{$connection}", []));
        }

        return parent::setUpTraits();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
