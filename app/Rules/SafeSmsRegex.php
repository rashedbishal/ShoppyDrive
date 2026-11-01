<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\SmsRegex;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeSmsRegex implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $problem = is_string($value) ? SmsRegex::problem($value) : 'This is not a valid regular expression.';

        if ($problem !== null) {
            $fail($problem);
        }
    }
}
