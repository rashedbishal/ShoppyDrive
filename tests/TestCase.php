<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
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
     * REL-004: no test writes to the real public disk, and no test can
     * silently make a real outbound HTTP call to Groq, Meta, Pathao or
     * Steadfast — a test missing its own Http::fake() now throws instead of
     * quietly trying the network (slow, flaky, and a real external side
     * effect from a test run). SSR is disabled first: Inertia's own SSR
     * gateway deliberately re-throws StrayRequestException instead of
     * falling back (vendor/inertiajs/inertia-laravel HttpGateway::dispatch()),
     * so without this, preventStrayRequests() would 500 every SSR-eligible
     * page in every test, not just the courier/AI-assistant ones it's
     * actually meant to catch. A handful of tests already disabled SSR
     * individually for unrelated reasons (PageSeoTagsTest and friends);
     * this makes it the default everywhere, since no test here asserts on
     * genuine SSR-rendered output.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['inertia.ssr.enabled' => false]);
        Http::preventStrayRequests();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
