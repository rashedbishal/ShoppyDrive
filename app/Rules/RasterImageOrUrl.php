<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Accepts either a plain string (an existing stored path/URL being kept
 * as-is — these fields double as "don't change the image" when no fresh
 * file is uploaded) or a freshly uploaded raster image. Deliberately
 * excludes SVG: an uploaded SVG is stored and served directly from public
 * storage with no sanitization, so its embedded <script>/event-handler
 * content executes in this app's own origin for anyone who opens the raw
 * file URL — a stored-XSS vector, not a theoretical one. Mime detection
 * (UploadedFile::getMimeType()) sniffs the actual file content, not the
 * client-supplied filename or Content-Type header.
 */
class RasterImageOrUrl implements ValidationRule
{
    /** @var list<string> */
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /** SEC-018: the same 5 MB limit as blog and product images. */
    private const MAX_KILOBYTES = 5120;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '' || is_string($value)) {
            return;
        }

        if (! $value instanceof UploadedFile) {
            $fail('The '.$attribute.' must be an image file or a valid URL.');

            return;
        }

        if (! in_array($value->getMimeType(), self::ALLOWED_MIMES, true)) {
            $fail('The '.$attribute.' must be a JPEG, PNG, WebP, or GIF image.');

            return;
        }

        if ($value->getSize() > self::MAX_KILOBYTES * 1024) {
            $fail('The '.$attribute.' must be 5MB or smaller.');
        }
    }
}
