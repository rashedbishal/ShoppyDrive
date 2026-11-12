<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Reads Laravel's own log files (storage/logs/laravel.log, or the
 * laravel-YYYY-MM-DD.log files the `daily` channel writes in production)
 * for Admin > Error Log, splitting the text into one entry per log record.
 * Read-only and file-based: no database involved.
 *
 * Only files whose names match Laravel's log naming are ever listed, read,
 * downloaded or cleared — a request can name a file, never a path. A log can
 * grow to hundreds of MB, so only its last WINDOW_BYTES are parsed.
 */
class LaravelLogReader
{
    /** Monolog's levels, most severe first. */
    public const LEVELS = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

    /** How much of the end of a file is read and parsed per request. */
    public const WINDOW_BYTES = 4 * 1024 * 1024;

    /** The full text sent for any one entry (long stack traces are cut). */
    private const MAX_ENTRY_TEXT = 20_000;

    private const FILE_NAME = '/^laravel(-\d{4}-\d{2}-\d{2})?\.log$/';

    /** `[2026-11-12 14:03:22] production.ERROR: ` — the start of every record. */
    private const ENTRY_START = '/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}[^\]]*)\] ([^\s.]+)\.([A-Za-z]+): /m';

    /** The exception Laravel writes into a record's context. */
    private const EXCEPTION = '/"exception":"\[object\] \(([^\s(]+)\(code: [^)]*\): .*? at (.+?):(\d+)\)/';

    private readonly string $directory;

    /** @param  string|null  $directory  defaults to storage/logs */
    public function __construct(?string $directory = null)
    {
        $this->directory = rtrim($directory ?? storage_path('logs'), '/');
    }

    /**
     * @return list<array{name: string, size: int, modified_at: int}> newest first
     */
    public function files(): array
    {
        $files = [];

        foreach (glob($this->directory.'/laravel*.log') ?: [] as $path) {
            $name = basename($path);

            if (preg_match(self::FILE_NAME, $name) === 1 && is_file($path)) {
                $files[] = ['name' => $name, 'size' => (int) filesize($path), 'modified_at' => (int) filemtime($path)];
            }
        }

        usort($files, fn (array $a, array $b): int => [$b['modified_at'], $b['name']] <=> [$a['modified_at'], $a['name']]);

        return $files;
    }

    /** The full path of a listed log file, or null for any other name. */
    public function path(string $name): ?string
    {
        foreach ($this->files() as $file) {
            if ($file['name'] === $name) {
                return $this->directory.'/'.$name;
            }
        }

        return null;
    }

    /**
     * The entries in the last WINDOW_BYTES of a listed file, newest first.
     *
     * @return array{entries: list<array{id: int, logged_at: string, environment: string, level: string, message: string, exception: string|null, location: string|null, text: string}>, truncated: bool}
     */
    public function read(string $name): array
    {
        $path = $this->path($name);

        if ($path === null) {
            return ['entries' => [], 'truncated' => false];
        }

        $size = (int) filesize($path);
        $offset = max(0, $size - self::WINDOW_BYTES);
        $content = (string) file_get_contents($path, false, null, $offset);

        preg_match_all(self::ENTRY_START, $content, $starts, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        $entries = [];
        $count = count($starts);

        foreach ($starts as $index => $match) {
            $begin = $match[0][1];
            $end = $index + 1 < $count ? $starts[$index + 1][0][1] : strlen($content);
            $entries[] = $this->entry($offset + $begin, $match, rtrim(substr($content, $begin, $end - $begin)));
        }

        return ['entries' => array_reverse($entries), 'truncated' => $offset > 0];
    }

    /** Empties a listed file (logging carries on into it); false for any other name. */
    public function clear(string $name): bool
    {
        $path = $this->path($name);

        return $path !== null && file_put_contents($path, '', LOCK_EX) !== false;
    }

    /**
     * @param  array<int, array{0: string, 1: int}>  $match
     * @return array{id: int, logged_at: string, environment: string, level: string, message: string, exception: string|null, location: string|null, text: string}
     */
    private function entry(int $id, array $match, string $text): array
    {
        $firstLine = strtok(substr($text, strlen($match[0][0])), "\n") ?: '';
        $exception = preg_match(self::EXCEPTION, $firstLine, $found) === 1 ? $found : null;

        return [
            'id' => $id,
            'logged_at' => $match[1][0],
            'environment' => $match[2][0],
            'level' => strtolower($match[3][0]),
            'message' => $this->message($firstLine),
            // JSON-escaped in the file: `App\\Exceptions\\Foo` → `App\Exceptions\Foo`.
            'exception' => $exception !== null ? stripslashes($exception[1]) : null,
            'location' => $exception !== null ? $this->relativePath(stripslashes($exception[2])).':'.$exception[3] : null,
            'text' => strlen($text) > self::MAX_ENTRY_TEXT
                ? substr($text, 0, self::MAX_ENTRY_TEXT)."\n… (cut — download the file for the full text)"
                : $text,
        ];
    }

    /** The record's message without the JSON context Laravel appends to it. */
    private function message(string $firstLine): string
    {
        $line = preg_replace('/ \[\]$/', '', $firstLine) ?? $firstLine;
        $contextStart = strpos($line, ' {"');

        $message = trim($contextStart === false ? $line : substr($line, 0, $contextStart));

        return mb_strimwidth($message, 0, 300, '…');
    }

    private function relativePath(string $path): string
    {
        $base = rtrim(base_path(), '/').'/';

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}
