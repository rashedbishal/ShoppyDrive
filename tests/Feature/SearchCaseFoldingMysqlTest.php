<?php

declare(strict_types=1);

use App\Models\Brand;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * MED-14. SQLite's LIKE only case-folds ASCII; MySQL's utf8mb4_unicode_ci
 * collation also folds accented Latin letters (É/é, Ï/ï, ...), so a search
 * that works on one driver can silently fail on the other. Bengali has no
 * case distinction at all, so it is deliberately not exercised here — this
 * finding is purely about Latin-script accented casing.
 *
 * The "on MySQL" group is a read-only proof of existing behavior, not an
 * application-code change: every assertion documents what MySQL already
 * does under utf8mb4_unicode_ci. It only runs when the configured
 * connection driver is mysql, and Tests\SafeTestDatabase (wired from
 * Tests\TestCase::setUpTraits()) independently refuses to let RefreshDatabase
 * touch any MySQL/MariaDB database whose name doesn't start with
 * "shoppydrive_test_" — so this file cannot reach a persistent database
 * under either guard.
 */
function assertColumnUsesUnicodeCollation(string $table, string $column): void
{
    $collation = DB::selectOne(
        'SELECT COLLATION_NAME AS collation FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$table, $column]
    )?->collation;

    expect($collation)->toBe(
        'utf8mb4_unicode_ci',
        "Expected {$table}.{$column} to use utf8mb4_unicode_ci so this test actually proves MED-14's MySQL behavior; found [".($collation ?? 'none').'] instead — fix the schema or this test\'s assumption before trusting the case-folding assertions below.'
    );
}

/**
 * The column collation alone doesn't prove what bytes actually got written —
 * a client/connection charset other than utf8mb4 could mangle the accented
 * fixtures at insert time (or at result-read time) before the LIKE
 * comparison even happens. Checked separately from the column check above.
 */
function assertSessionUsesUtf8mb4(): void
{
    $variables = collect(DB::select(
        "SHOW SESSION VARIABLES WHERE Variable_name IN ('character_set_connection', 'character_set_client', 'character_set_results')"
    ))->mapWithKeys(fn ($row) => [$row->Variable_name => $row->Value]);

    foreach (['character_set_connection', 'character_set_client', 'character_set_results'] as $name) {
        expect($variables->get($name))->toBe(
            'utf8mb4',
            "Expected MySQL session variable [{$name}] to be utf8mb4 so this test actually proves MED-14's MySQL behavior; found [".($variables->get($name) ?? 'unknown').'] instead — fix the connection configuration before trusting the case-folding assertions below.'
        );
    }
}

describe('on SQLite', function () {
    beforeEach(function () {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('covers the SQLite LIKE baseline and documented limitation');
        }
    });

    test('ASCII case-insensitive LIKE matches regardless of letter case', function () {
        Brand::factory()->create(['name' => 'SHIRT Co']);
        Brand::factory()->create(['name' => 'Pants Co']);

        expect(Brand::query()->where('name', 'like', '%shirt%')->pluck('name')->all())->toBe(['SHIRT Co']);
    });

    test('LIKE does not case-fold the accented letter alone — the documented MED-14 limitation', function () {
        Brand::factory()->create(['name' => 'café Co']);
        Brand::factory()->create(['name' => 'Decoy Co']);

        // Control: exact case, exact accent — the query mechanism itself is sound.
        expect(Brand::query()->where('name', 'like', '%café%')->pluck('name')->all())->toBe(['café Co']);

        // The gap, isolated: only the accented letter's case changes (É vs é) — every
        // other character in the pattern is identical to the control query above.
        expect(Brand::query()->where('name', 'like', '%cafÉ%')->pluck('name')->all())->toBe([]);
    });
});

describe('on MySQL', function () {
    beforeEach(function () {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('requires real MySQL to observe utf8mb4_unicode_ci case-folding');
        }

        assertSessionUsesUtf8mb4();
        assertColumnUsesUnicodeCollation('brands', 'name');
        assertColumnUsesUnicodeCollation('users', 'name');
        assertColumnUsesUnicodeCollation('users', 'email');
    });

    test('ASCII case-insensitive LIKE still matches, same as SQLite', function () {
        Brand::factory()->create(['name' => 'SHIRT Co']);
        Brand::factory()->create(['name' => 'Pants Co']);

        expect(Brand::query()->where('name', 'like', '%shirt%')->pluck('name')->all())->toBe(['SHIRT Co']);
    });

    test('utf8mb4_unicode_ci case-folds accented Latin letters in LIKE, unlike SQLite', function () {
        Brand::factory()->create(['name' => 'Café Co']);
        Brand::factory()->create(['name' => 'Naïve Co']);
        Brand::factory()->create(['name' => 'Decoy Co']);

        // An uppercase, differently-accented-looking query still finds the stored value.
        expect(Brand::query()->where('name', 'like', '%CAFÉ%')->pluck('name')->all())->toBe(['Café Co']);

        // The reverse direction: a lowercase query finds the capitalized stored value.
        expect(Brand::query()->where('name', 'like', '%naïve%')->pluck('name')->all())->toBe(['Naïve Co']);

        // Control: an unrelated name is never swept in by a loose match.
        expect(Brand::query()->where('name', 'like', '%CAFÉ%')->pluck('name')->all())->not->toContain('Decoy Co');
    });

    test('customer-facing search suggestions match a brand by accented-case-insensitive name', function () {
        $matching = Brand::factory()->create(['name' => 'Café Deluxe', 'is_active' => true]);
        Brand::factory()->create(['name' => 'Unrelated Brand', 'is_active' => true]);

        $response = $this->getJson('/api/search/suggestions?q='.urlencode('CAFÉ'));

        $response->assertOk();
        expect(collect($response->json('brands'))->pluck('id')->all())->toBe([$matching->id]);
        expect(collect($response->json('brands'))->pluck('name')->all())->toBe(['Café Deluxe']);
    });

    test('admin brand filter matches by accented-case-insensitive name', function () {
        $admin = createAdmin();
        $matching = Brand::factory()->create(['name' => 'Café Deluxe']);
        Brand::factory()->create(['name' => 'Unrelated Brand']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.brands.index', ['search' => 'CAFÉ']))
            ->assertInertia(fn ($page) => $page
                ->component('admin/brands/index')
                ->has('brands.data', 1)
                ->where('brands.data.0.id', $matching->id)
            );
    });

    test('admin customer filter matches by accented-case-insensitive name across the OR LIKE clause', function () {
        $admin = createAdmin();
        $matching = User::factory()->create(['name' => 'José García', 'email' => 'jose@example.com']);
        User::factory()->create(['name' => 'Someone Else', 'email' => 'someone@example.com']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.index', ['search' => 'JOSÉ']))
            ->assertInertia(fn ($page) => $page
                ->component('admin/customers/index')
                ->has('customers.data', 1)
                ->where('customers.data.0.id', $matching->id)
            );
    });
});
