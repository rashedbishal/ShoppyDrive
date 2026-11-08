<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The "check mechanism" for Custom Header/Footer code: not a strict format
 * match, since this field is deliberately vendor-agnostic (Google Tag
 * Manager today, another vendor's snippet tomorrow) and a content check
 * tied to one vendor's shape would just break on the next one. Instead,
 * this catches the realistic mistake — a truncated or half-pasted
 * snippet — by requiring every opened <script>/<noscript> tag to have a
 * matching close. An empty value always passes; clearing the field is
 * always allowed.
 */
class BalancedScriptTags implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        foreach (['script', 'noscript'] as $tag) {
            $opens = preg_match_all('/<'.$tag.'\b/i', $value);
            $closes = preg_match_all('/<\/'.$tag.'>/i', $value);

            if ($opens !== $closes) {
                $fail("This code has a mismatched <{$tag}> tag — check it was pasted in full.");

                return;
            }
        }
    }
}
