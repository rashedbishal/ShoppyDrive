<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;

/**
 * Whether customers may attach photos to a review, and how many, as set in
 * Admin > Settings. Off by default. The standard limits below are fixed (not
 * admin-editable) and are what the customer-facing form and the admin settings
 * page both state.
 */
class ReviewImageSettings
{
    public const ENABLED_KEY = 'review_images_enabled';

    public const MAX_PER_REVIEW_KEY = 'review_images_max';

    /** Photos per review when the admin has not chosen a number. */
    public const DEFAULT_MAX_PER_REVIEW = 3;

    /** The most an admin may allow per review. */
    public const MAX_PER_REVIEW_LIMIT = 5;

    /** Largest accepted file, in kilobytes (5 MB, the same as product photos). */
    public const MAX_FILE_KILOBYTES = 5120;

    /** Smallest accepted photo, in pixels, on each side. */
    public const MIN_SIDE_PIXELS = 200;

    /** Largest accepted photo, in pixels, on each side (a safety limit, not a target). */
    public const MAX_SIDE_PIXELS = 10000;

    /** Stored photos are resized so their longest side is at most this (ImageOptimizer::MAX_SIDE). */
    public const STORED_MAX_SIDE_PIXELS = 1600;

    /** @var list<string> */
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public static function enabled(): bool
    {
        return (bool) (Setting::where('key', self::ENABLED_KEY)->first()?->value ?? false);
    }

    /** Always between 1 and MAX_PER_REVIEW_LIMIT, whatever is stored. */
    public static function maxPerReview(): int
    {
        $stored = Setting::where('key', self::MAX_PER_REVIEW_KEY)->first()?->value;
        $value = is_numeric($stored) ? (int) $stored : self::DEFAULT_MAX_PER_REVIEW;

        return max(1, min(self::MAX_PER_REVIEW_LIMIT, $value));
    }
}
