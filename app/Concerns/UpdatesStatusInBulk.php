<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait UpdatesStatusInBulk
{
    /**
     * Validate a bulk status request and toggle the given boolean column on every selected record.
     *
     * @param  class-string<Model>  $modelClass
     * @param  (callable(Model): void)|null  $afterUpdate
     * @return array{count: int, active: bool}
     */
    protected function updateStatusInBulk(Request $request, string $modelClass, string $column = 'is_active', ?callable $afterUpdate = null): array
    {
        $table = (new $modelClass)->getTable();

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', "exists:{$table},id"],
            'active' => ['required', 'boolean'],
        ]);

        $isActive = (bool) $validated['active'];

        $modelClass::whereIn('id', $validated['ids'])->get()->each(function (Model $record) use ($column, $isActive, $afterUpdate): void {
            $record->{$column} = $isActive;
            $record->save();

            if ($afterUpdate !== null) {
                $afterUpdate($record);
            }
        });

        return ['count' => count($validated['ids']), 'active' => $isActive];
    }
}
