<?php

declare(strict_types=1);

namespace App\Support\Dashboard;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The dashboard's selected period, in Dhaka calendar days (BE-09), plus the
 * period of the same length just before it for "vs previous" comparisons.
 * Anything unrecognised falls back to the last 30 days.
 */
final class DashboardRange
{
    public const TIMEZONE = 'Asia/Dhaka';

    public const PRESETS = ['today', '7d', '30d', '90d', 'month', 'custom'];

    public const DEFAULT = '30d';

    /** A custom range can't exceed a year, so one request stays cheap. */
    public const MAX_DAYS = 366;

    private function __construct(
        public readonly string $preset,
        /** Dhaka midnight of the first day. */
        public readonly Carbon $start,
        /** Dhaka 23:59:59 of the last day (today's is still in progress). */
        public readonly Carbon $end,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $preset = (string) $request->query('range', self::DEFAULT);
        $today = Carbon::now(self::TIMEZONE)->startOfDay();

        return match ($preset) {
            'today' => new self('today', $today, $today->copy()->endOfDay()),
            '7d' => new self('7d', $today->copy()->subDays(6), $today->copy()->endOfDay()),
            '90d' => new self('90d', $today->copy()->subDays(89), $today->copy()->endOfDay()),
            'month' => new self('month', $today->copy()->startOfMonth(), $today->copy()->endOfDay()),
            'custom' => self::custom($request, $today),
            default => new self(self::DEFAULT, $today->copy()->subDays(29), $today->copy()->endOfDay()),
        };
    }

    private static function custom(Request $request, Carbon $today): self
    {
        $from = self::parseDate($request->query('from'));
        $to = self::parseDate($request->query('to'));

        if ($from === null || $to === null) {
            return new self(self::DEFAULT, $today->copy()->subDays(29), $today->copy()->endOfDay());
        }

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        if ($to->greaterThan($today)) {
            $to = $today->copy();
        }

        if ($from->greaterThan($to)) {
            $from = $to->copy();
        }

        if ($from->diffInDays($to) + 1 > self::MAX_DAYS) {
            $from = $to->copy()->subDays(self::MAX_DAYS - 1);
        }

        return new self('custom', $from, $to->copy()->endOfDay());
    }

    private static function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $value, self::TIMEZONE);
        } catch (\Throwable) {
            return null;
        }

        return $date !== null && $date->format('Y-m-d') === $value ? $date->startOfDay() : null;
    }

    public function days(): int
    {
        return (int) $this->start->diffInDays($this->end->copy()->startOfDay()) + 1;
    }

    /** The same number of days immediately before this range. */
    public function previous(): self
    {
        $end = $this->start->copy()->subDay()->endOfDay();

        return new self($this->preset, $end->copy()->startOfDay()->subDays($this->days() - 1), $end);
    }

    /**
     * For whereBetween on UTC-stored timestamps.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function utc(): array
    {
        return [$this->start->copy()->utc(), $this->end->copy()->utc()];
    }

    /**
     * Chart buckets: hours for a single day, days up to 92 days, weeks beyond.
     */
    public function bucketUnit(): string
    {
        return match (true) {
            $this->days() === 1 => 'hour',
            $this->days() <= 92 => 'day',
            default => 'week',
        };
    }

    /** The bucket a Dhaka-local time falls in. */
    public function bucketKey(CarbonInterface $dhakaTime): string
    {
        return match ($this->bucketUnit()) {
            'hour' => $dhakaTime->format('H'),
            'day' => $dhakaTime->toDateString(),
            default => (string) (int) floor($this->start->diffInDays($dhakaTime->copy()->startOfDay()) / 7),
        };
    }

    /**
     * Every bucket in order, with its label.
     *
     * @return list<array{key: string, label: string}>
     */
    public function buckets(): array
    {
        $buckets = [];

        if ($this->bucketUnit() === 'hour') {
            foreach (range(0, 23) as $hour) {
                $buckets[] = ['key' => sprintf('%02d', $hour), 'label' => Carbon::createFromTime($hour)->format('g A')];
            }

            return $buckets;
        }

        if ($this->bucketUnit() === 'day') {
            for ($day = $this->start->copy(); $day->lessThanOrEqualTo($this->end); $day->addDay()) {
                $buckets[] = ['key' => $day->toDateString(), 'label' => $day->format('j M')];
            }

            return $buckets;
        }

        $weeks = (int) ceil($this->days() / 7);

        foreach (range(0, $weeks - 1) as $week) {
            $buckets[] = ['key' => (string) $week, 'label' => $this->start->copy()->addDays($week * 7)->format('j M')];
        }

        return $buckets;
    }

    public function cacheKey(): string
    {
        return $this->start->toDateString().'_'.$this->end->toDateString();
    }

    /**
     * @return array{preset: string, from: string, to: string, days: int, label: string}
     */
    public function toArray(): array
    {
        return [
            'preset' => $this->preset,
            'from' => $this->start->toDateString(),
            'to' => $this->end->toDateString(),
            'days' => $this->days(),
            'label' => $this->days() === 1
                ? $this->start->format('j M Y')
                : $this->start->format('j M').' – '.$this->end->format('j M Y'),
        ];
    }
}
