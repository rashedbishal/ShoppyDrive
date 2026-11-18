<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Illuminate\Support\Fluent;

/**
 * Records what each column would hold on MySQL/MariaDB while the migrations
 * run on SQLite. SQLite stores every string as an unbounded `varchar`/`text`
 * (SQLiteGrammar::typeString() drops the length), so a validation limit
 * bigger than its column passes the SQLite suite and then fails the insert
 * on production (DISC-68). The tests run on SQLite, so this reads the
 * migrations' own column definitions instead of a MySQL schema.
 */
class ColumnCapacityRecorder extends SQLiteGrammar
{
    /** utf8mb4 stores up to 4 bytes per character. */
    private const BYTES_PER_CHARACTER = 4;

    /** @var array<string, int> MySQL TEXT-family capacities in bytes. */
    private const TEXT_BYTES = [
        'tinyText' => 255,
        'text' => 65535,
        'mediumText' => 16777215,
        'longText' => 4294967295,
    ];

    /** @var array<string, int> MySQL column types Laravel sizes itself. */
    private const FIXED_TEXT_CHARACTERS = [
        'uuid' => 36,
        'ulid' => 26,
        'ipAddress' => 45,
        'macAddress' => 17,
    ];

    /** @var array<string, array{0: int, 1: int}> signed integer ranges */
    private const INTEGER_RANGES = [
        'tinyInteger' => [-128, 127],
        'smallInteger' => [-32768, 32767],
        'mediumInteger' => [-8388608, 8388607],
        'integer' => [-2147483648, 2147483647],
    ];

    /**
     * @var array<string, array<string, array{kind: 'text', characters: int}|array{kind: 'number', min: float, max: float}>>
     */
    public array $tables = [];

    public function compileCreate(Blueprint $blueprint, Fluent $command)
    {
        $this->tables[$blueprint->getTable()] = [];
        $this->record($blueprint->getTable(), $blueprint->getAddedColumns());

        return parent::compileCreate($blueprint, $command);
    }

    public function compileAdd(Blueprint $blueprint, Fluent $command)
    {
        $this->record($blueprint->getTable(), [$command->column]);

        return parent::compileAdd($blueprint, $command);
    }

    public function compileChange(Blueprint $blueprint, Fluent $command)
    {
        $this->record($blueprint->getTable(), [$command->column]);

        return parent::compileChange($blueprint, $command);
    }

    public function compileRenameColumn(Blueprint $blueprint, Fluent $command)
    {
        $table = $blueprint->getTable();

        if (isset($this->tables[$table][$command->from])) {
            $this->tables[$table][$command->to] = $this->tables[$table][$command->from];
            unset($this->tables[$table][$command->from]);
        }

        return parent::compileRenameColumn($blueprint, $command);
    }

    public function compileDropColumn(Blueprint $blueprint, Fluent $command)
    {
        foreach ($command->columns as $column) {
            unset($this->tables[$blueprint->getTable()][$column]);
        }

        return parent::compileDropColumn($blueprint, $command);
    }

    public function compileRename(Blueprint $blueprint, Fluent $command)
    {
        $this->tables[$command->to] = $this->tables[$blueprint->getTable()] ?? [];
        unset($this->tables[$blueprint->getTable()]);

        return parent::compileRename($blueprint, $command);
    }

    public function compileDrop(Blueprint $blueprint, Fluent $command)
    {
        unset($this->tables[$blueprint->getTable()]);

        return parent::compileDrop($blueprint, $command);
    }

    public function compileDropIfExists(Blueprint $blueprint, Fluent $command)
    {
        unset($this->tables[$blueprint->getTable()]);

        return parent::compileDropIfExists($blueprint, $command);
    }

    /**
     * @param  iterable<Fluent>  $columns
     */
    private function record(string $table, iterable $columns): void
    {
        foreach ($columns as $column) {
            $capacity = $this->capacityOf($column);

            if ($capacity === null) {
                unset($this->tables[$table][$column->name]);
            } else {
                $this->tables[$table][$column->name] = $capacity;
            }
        }
    }

    /**
     * @return array{kind: 'text', characters: int}|array{kind: 'number', min: float, max: float}|null
     */
    private function capacityOf(Fluent $column): ?array
    {
        $type = (string) $column->type;

        if (in_array($type, ['string', 'char'], true)) {
            return ['kind' => 'text', 'characters' => (int) $column->length];
        }

        if (isset(self::FIXED_TEXT_CHARACTERS[$type])) {
            return ['kind' => 'text', 'characters' => self::FIXED_TEXT_CHARACTERS[$type]];
        }

        if (isset(self::TEXT_BYTES[$type])) {
            return ['kind' => 'text', 'characters' => intdiv(self::TEXT_BYTES[$type], self::BYTES_PER_CHARACTER)];
        }

        if (isset(self::INTEGER_RANGES[$type])) {
            [$min, $max] = self::INTEGER_RANGES[$type];

            return $column->unsigned
                ? ['kind' => 'number', 'min' => 0, 'max' => $max * 2 + 1]
                : ['kind' => 'number', 'min' => $min, 'max' => $max];
        }

        if ($type === 'decimal') {
            $max = 10 ** ((int) $column->total - (int) $column->places) - 10 ** -((int) $column->places);

            return ['kind' => 'number', 'min' => $column->unsigned ? 0 : -$max, 'max' => $max];
        }

        // json, bigInteger, float, boolean, dates, enums, ...: no limit an
        // admin-typed value can realistically exceed, or none to compare.
        return null;
    }
}
