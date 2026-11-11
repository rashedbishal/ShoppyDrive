<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\MetaTrackingEvent;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * What the storefront may report for a Meta event: which event, its id,
 * the page, and *which* products/quantities or search — never prices,
 * totals or customer details, which the server looks up itself. Purchase is
 * not accepted at all; it only ever comes from a real order.
 */
class MetaTrackingEventRequest extends FormRequest
{
    public const MAX_ITEMS = 50;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isSearch = $this->input('event_name') === 'Search';

        return [
            'event_name' => ['required', 'string', Rule::in(MetaTrackingEvent::BROWSER_EVENTS)],
            'event_id' => ['required', 'uuid'],
            'event_source_url' => ['required', 'string', 'max:2000', 'url:http,https', $this->sameSiteUrl(...)],
            'items' => [$isSearch ? 'nullable' : 'required', 'array', $isSearch ? 'min:0' : 'min:1', 'max:'.self::MAX_ITEMS],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.variant_id' => ['nullable', 'integer', 'min:1'],
            'items.*.bundle_id' => ['nullable', 'integer', 'min:1'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'search_string' => [$isSearch ? 'required' : 'prohibited', 'string', 'max:100'],
            'coupon' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * The event must have happened on this site: an event_source_url on
     * another host would let anyone attribute traffic to arbitrary pages.
     */
    private function sameSiteUrl(string $attribute, mixed $value, Closure $fail): void
    {
        $host = parse_url((string) $value, PHP_URL_HOST);

        if (! is_string($host) || strcasecmp($host, $this->getHost()) !== 0) {
            $fail('The event must come from this site.');
        }
    }
}
