<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Throwable;

/**
 * SEC-023: the only fields written to the log from courier traffic and
 * unexpected payment errors. Log files are readable text kept for weeks, so raw
 * webhook payloads, courier response bodies and SQL error text (whose
 * bindings include addresses) must never reach it. The order number or
 * consignment ID leads to the details in Admin.
 */
final class SafeLogContext
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{event: mixed, consignment_id: mixed, invoice: mixed, status: mixed}
     */
    public static function steadfastWebhook(array $payload): array
    {
        return [
            'event' => is_scalar($payload['notification_type'] ?? null) ? $payload['notification_type'] : null,
            'consignment_id' => is_scalar($payload['consignment_id'] ?? null) ? $payload['consignment_id'] : null,
            'invoice' => is_scalar($payload['invoice'] ?? null) ? $payload['invoice'] : null,
            'status' => is_scalar($payload['status'] ?? null) ? $payload['status'] : null,
        ];
    }

    /**
     * The HTTP status, a short error message and which fields failed: never
     * the field values, which can repeat a customer's address or phone.
     *
     * @param  mixed  $json  the decoded response body; anything but an array is ignored
     * @return array{http_status: int|null, message: string|null, error_fields: list<int|string>}
     */
    public static function courierResponse(mixed $json, ?int $httpStatus = null): array
    {
        $message = is_array($json) ? ($json['message'] ?? null) : null;
        $errors = is_array($json) ? ($json['errors'] ?? null) : null;

        return [
            'http_status' => $httpStatus,
            'message' => is_string($message) ? Str::limit($message, 200) : null,
            'error_fields' => is_array($errors) ? array_keys($errors) : [],
        ];
    }

    /**
     * @return array{exception: class-string, code: int|string, sql_state: string|null}
     */
    public static function exception(Throwable $e): array
    {
        return [
            'exception' => $e::class,
            'code' => $e->getCode(),
            'sql_state' => $e instanceof QueryException ? ($e->errorInfo[0] ?? null) : null,
        ];
    }

    /**
     * SEC-023: a failed or malformed Groq response, with never the message
     * text — unlike a courier's validation error (a field name, e.g.
     * "recipient_phone is required"), an LLM API's error message commonly
     * quotes the offending input back verbatim (a content-policy violation
     * naming the exact text that tripped it), and that input is this
     * customer's own typed chat message. `type`/`code` are a fixed,
     * API-defined classifier, never free text, so they're safe to keep.
     *
     * @param  mixed  $json  the decoded response body; anything but an array is ignored
     * @return array{http_status: int|null, type: string|null, code: string|null}
     */
    public static function aiProviderResponse(mixed $json, ?int $httpStatus = null): array
    {
        $error = is_array($json) ? ($json['error'] ?? null) : null;

        return [
            'http_status' => $httpStatus,
            'type' => is_scalar($error['type'] ?? null) ? (string) $error['type'] : null,
            'code' => is_scalar($error['code'] ?? null) ? (string) $error['code'] : null,
        ];
    }

    /**
     * A successful Groq response that didn't have the shape expected
     * (`choices[0].message` missing) — only the top-level keys actually
     * present, never their values, which can hold this customer's own
     * typed chat message echoed back inside an otherwise-malformed reply.
     *
     * @param  mixed  $json  the decoded response body; anything but an array is ignored
     * @return array{keys: list<int|string>}
     */
    public static function aiProviderResponseShape(mixed $json): array
    {
        return [
            'keys' => is_array($json) ? array_keys($json) : [],
        ];
    }
}
