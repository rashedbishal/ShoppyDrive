<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\ColumnCapacityRecorder;
use Tests\ValidationLimitScanner;

/*
 * DISC-68: a validation limit larger than its database column lets a value
 * through that MySQL then refuses, so the admin gets a server error instead
 * of a form message (Add Product: 300-character limit, VARCHAR(255) column).
 * The suite runs on SQLite, which ignores column sizes, so no ordinary test
 * can catch it. This one compares every validation rule in app/ with the
 * MySQL size of every column of the same name.
 *
 * When it fails: lower the rule to fit the column, or widen the column in a
 * migration. Add to COLUMN_IS_NOT_WRITTEN only when that field really never
 * reaches that column, with the reason.
 */

/**
 * Raw SQL column changes the recorder cannot see (it only sees Blueprint
 * calls), with the size they leave on MySQL.
 *
 * @var array<string, array<string, array{kind: 'text', characters: int}>>
 */
const RAW_SQL_COLUMN_SIZES = [
    // 2026_10_25_115849: ALTER TABLE users MODIFY referral_code VARCHAR(12)
    'users' => ['referral_code' => ['kind' => 'text', 'characters' => 12]],
];

/** Migrations allowed to change a column's size with raw SQL (above). */
const RAW_SQL_SIZE_MIGRATIONS = ['2026_10_25_115849_widen_referral_code_column_on_users_table.php'];

/**
 * A field that shares its name with a column it is never saved into:
 * "file:field" => list of "table.column", each with its reason.
 *
 * @var array<string, array<string, string>>
 */
const COLUMN_IS_NOT_WRITTEN = [
    // ProductController also uses Brand/Category/SubCategory, but the product
    // description is only ever saved to products.description.
    'Http/Requests/Admin/ProductRequest.php:description' => [
        'brands.description' => 'product form never writes brands',
        'categories.description' => 'product form never writes categories',
        'sub_categories.description' => 'product form never writes sub-categories',
    ],
    // Only logged, cut to 300 characters; nothing is saved.
    'Http/Controllers/ClientErrorController.php:path' => [
        'product_media.path' => 'browser crash reports are logged, not stored',
    ],
];

/**
 * @return array<string, array<string, array{kind: 'text', characters: int}|array{kind: 'number', min: float, max: float}>>
 */
function mysqlColumnCapacities(): array
{
    config(['database.connections.column_capacity_probe' => [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]]);

    $connection = DB::connection('column_capacity_probe');
    $recorder = new ColumnCapacityRecorder($connection);
    $connection->setSchemaGrammar($recorder);

    Artisan::call('migrate', ['--database' => 'column_capacity_probe', '--force' => true]);

    DB::purge('column_capacity_probe');

    return array_replace_recursive($recorder->tables, RAW_SQL_COLUMN_SIZES);
}

/**
 * A limit read from a variable, with why it fits: "file:field" => reason.
 *
 * @var array<string, string>
 */
const LIMIT_IS_A_RUNTIME_VALUE = [
    // $max is the page's "most units per order" (Admin › Landing pages,
    // validated 1..100), far inside the INT order/cart quantity columns.
    'Http/Controllers/LandingPageController.php:quantity' => 'landing page max_order_quantity, 1..100',
    'Http/Controllers/LandingPageController.php:items.*.quantity' => 'landing page max_order_quantity, 1..100',
    'Http/Controllers/LandingPageController.php:combos.*.quantity' => 'landing page max_order_quantity, 1..100',
];

/**
 * The tables a rule's value can be saved into: those of the models its file
 * works with and, for a form request, of the controllers that receive it.
 * A file that names no model is compared with every table.
 *
 * @param  array{file: string, models: list<string>}  $rule
 * @param  array<string, mixed>  $columns
 * @return list<string>
 */
function tablesWrittenBy(array $rule, array $columns): array
{
    static $modelsByRequest = [];

    $models = $rule['models'];

    if (str_starts_with($rule['file'], 'Http/Requests/')) {
        $request = basename($rule['file'], '.php');

        $modelsByRequest[$request] ??= collect(glob(app_path('Http/Controllers/{,*/,*/*/}*.php'), GLOB_BRACE))
            ->map(fn (string $path): string => (string) file_get_contents($path))
            ->filter(fn (string $source): bool => (bool) preg_match('/\\b'.$request.'\\s+\\$/', $source))
            ->flatMap(fn (string $source): array => ValidationLimitScanner::modelsUsedBy($source))
            ->all();

        $models = [...$models, ...$modelsByRequest[$request]];
    }

    $tables = collect($models)
        ->unique()
        ->filter(fn (string $model): bool => class_exists($model) && is_subclass_of($model, Model::class) && ! (new ReflectionClass($model))->isAbstract())
        ->map(fn (string $model): string => (new $model)->getTable())
        ->filter(fn (string $table): bool => isset($columns[$table]))
        ->values()
        ->all();

    return $tables !== [] ? $tables : array_keys($columns);
}

test('no migration changes a column size with raw SQL the check cannot see', function () {
    $rawSizeChanges = collect(glob(database_path('migrations/*.php')))
        ->filter(fn (string $path): bool => (bool) preg_match('/\b(?:MODIFY|CHANGE|ALTER\s+COLUMN)\b[^;\'"]*\b(?:VARCHAR|CHAR|TEXT|INT|INTEGER|DECIMAL)\b/i', (string) file_get_contents($path)))
        ->map(fn (string $path): string => basename($path))
        ->values()
        ->all();

    expect($rawSizeChanges)->toBe(RAW_SQL_SIZE_MIGRATIONS);
});

test('every validation limit fits the column it can be saved into', function () {
    $columns = mysqlColumnCapacities();
    $rules = ValidationLimitScanner::scan(app_path());

    expect(count($columns))->toBeGreaterThan(50)
        ->and(count($rules))->toBeGreaterThan(200);

    $problems = [];

    foreach ($rules as $rule) {
        $where = "app/{$rule['file']}:{$rule['line']} '{$rule['field']}'";

        if ($rule['unresolved'] !== [] && ! isset(LIMIT_IS_A_RUNTIME_VALUE["{$rule['file']}:{$rule['field']}"])) {
            $problems[] = "{$where}: cannot read its ".implode('/', $rule['unresolved']).' limit; write it as a literal or a class constant';
        }

        $name = last(explode('.', $rule['field']));
        $ignored = COLUMN_IS_NOT_WRITTEN["{$rule['file']}:{$rule['field']}"] ?? [];

        foreach (tablesWrittenBy($rule, $columns) as $table) {
            $column = $columns[$table][$name] ?? null;

            if ($column === null || isset($ignored["{$table}.{$name}"]) || $column['kind'] !== $rule['kind']) {
                continue;
            }

            if ($column['kind'] === 'text' && $rule['max'] !== null && $rule['max'] > $column['characters']) {
                $problems[] = "{$where} (max {$rule['max']}) > {$table}.{$name} ({$column['characters']} characters)";
            }

            if ($column['kind'] === 'number' && (($rule['max'] !== null && $rule['max'] > $column['max']) || ($rule['min'] !== null && $rule['min'] < $column['min']))) {
                $problems[] = "{$where} (".($rule['min'] ?? '…').'..'.($rule['max'] ?? '…').") outside {$table}.{$name} ({$column['min']}..{$column['max']})";
            }
        }
    }

    expect($problems)->toBe([]);
});
