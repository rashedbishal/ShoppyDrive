<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;
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

    /**
     * REL-004: no test writes to the real public disk.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
