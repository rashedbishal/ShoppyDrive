<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\Mp4Video;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * DISC-71: keeps uploaded videos light enough to start quickly on a phone.
 * Shared hosting can't re-encode a video, so the admin is asked to upload
 * one that is already web-sized, and every message says how to make one.
 *
 * Only applies to uploads that are videos (anything else passes through for
 * the field's other rules). MP4/MOV files are checked for resolution and,
 * when `$requireH264`, for the H.264 codec every browser plays. Other video
 * formats (e.g. WebM) can't be inspected without ffmpeg, so they get the
 * size limit only, or are refused outright when H.264 is required.
 */
class WebVideo implements ValidationRule
{
    /** 720p in either orientation: 1280×720 landscape or 720×1280 upright. */
    public const int MAX_LONG_SIDE = 1280;

    public const int MAX_SHORT_SIDE = 720;

    public const string HOW_TO_FIX = 'Tip: open it in the free HandBrake app, choose the "Fast 720p30" preset, tick "Web Optimized", remove the audio track, and save it again.';

    /** @var list<string> */
    private const array MP4_MIMES = ['video/mp4', 'video/quicktime'];

    /** @var list<string> `avc1`/`avc3` are H.264's sample-entry codes. */
    private const array H264_CODECS = ['avc1', 'avc3'];

    public function __construct(
        private readonly int $maxMegabytes,
        private readonly bool $requireH264,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $mime = (string) $value->getMimeType();

        if (! str_starts_with($mime, 'video/')) {
            return;
        }

        if ($value->getSize() > $this->maxMegabytes * 1024 * 1024) {
            $fail("Videos must be {$this->maxMegabytes} MB or smaller. ".self::HOW_TO_FIX.' If it is still too big, shorten the clip.');

            return;
        }

        if (! in_array($mime, self::MP4_MIMES, true)) {
            if ($this->requireH264) {
                $fail('Videos must be MP4 files using H.264 video. '.self::HOW_TO_FIX);
            }

            return;
        }

        $video = app(Mp4Video::class)->inspect((string) $value->getRealPath());

        if ($video === null) {
            $fail('This video could not be read. Save it again as an MP4 file. '.self::HOW_TO_FIX);

            return;
        }

        if ($this->requireH264 && ! in_array($video['codec'], self::H264_CODECS, true)) {
            $fail('Videos must use H.264 video so every browser can play them. '.self::HOW_TO_FIX);

            return;
        }

        $longSide = max($video['width'], $video['height']);
        $shortSide = min($video['width'], $video['height']);

        if ($longSide > self::MAX_LONG_SIDE || $shortSide > self::MAX_SHORT_SIDE) {
            $fail("This video is {$video['width']}×{$video['height']} pixels. Videos must be 720p or smaller (at most 1280×720, or 720×1280 upright). ".self::HOW_TO_FIX);
        }
    }
}
