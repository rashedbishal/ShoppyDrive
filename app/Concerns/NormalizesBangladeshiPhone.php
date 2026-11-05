<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Support\BangladeshiPhone;

/**
 * FE-012: cleans the request's `phone` field (Bangla digits, +880, spaces,
 * dashes) before validation, so every form applies the same rule.
 */
trait NormalizesBangladeshiPhone
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('phone'))) {
            $this->merge(['phone' => BangladeshiPhone::normalize($this->input('phone'))]);
        }
    }
}
