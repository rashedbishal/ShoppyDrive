<?php

declare(strict_types=1);

namespace App\Concerns;

use LogicException;

/**
 * PAY-RETURN-001: rows of these models are written once and never changed
 * or removed by the application. This is application-level protection only
 * — it does not stop direct database modification.
 */
trait AppendOnly
{
    protected static function bootAppendOnly(): void
    {
        static::updating(function ($model): never {
            throw new LogicException(class_basename($model).' records are append-only and cannot be updated.');
        });

        static::deleting(function ($model): never {
            throw new LogicException(class_basename($model).' records are append-only and cannot be deleted.');
        });
    }
}
