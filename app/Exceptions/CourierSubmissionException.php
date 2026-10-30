<?php

declare(strict_types=1);

namespace App\Exceptions;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

/**
 * A courier "create order" call that did not return a usable consignment,
 * classified by whether the courier may nevertheless have created one.
 *
 * Only outcomes where the create request provably never reached the courier,
 * or the courier explicitly rejected it, are definite failures. Everything
 * else — timeouts and dropped connections after the request may have been
 * sent, 5xx, odd success payloads — is an unknown outcome, which must never
 * be retried automatically (see CourierSubmissionService).
 */
class CourierSubmissionException extends RuntimeException
{
    /**
     * cURL errors that happen before a single request byte can be sent:
     * CURLE_COULDNT_RESOLVE_PROXY, CURLE_COULDNT_RESOLVE_HOST,
     * CURLE_COULDNT_CONNECT.
     *
     * @var list<int>
     */
    private const PRE_TRANSMISSION_CURL_ERRORS = [5, 6, 7];

    /**
     * HTTP statuses that don't prove the order wasn't created: a request
     * timeout, and a conflict that may mean "already exists".
     *
     * @var list<int>
     */
    private const AMBIGUOUS_CLIENT_ERRORS = [408, 409];

    public function __construct(
        string $message,
        public readonly bool $outcomeUnknown,
        public readonly string $failureKind,
        public readonly ?int $httpStatus = null,
        public readonly ?int $curlErrno = null,
        public readonly ?int $requestBytesSent = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function notSent(string $message, ?Throwable $previous = null): self
    {
        return new self($message, false, 'not_sent', previous: $previous);
    }

    public static function unknown(string $failureKind, string $message, ?int $httpStatus = null, ?Throwable $previous = null): self
    {
        return new self($message, true, $failureKind, $httpStatus, previous: $previous);
    }

    /**
     * Uses the cURL error number and the number of request bytes cURL
     * reports as sent (from Guzzle's handler context) — without that
     * evidence the outcome is treated as unknown.
     */
    public static function fromConnectionException(string $courier, ConnectionException $e): self
    {
        $transport = $e->getPrevious();
        $context = $transport instanceof RequestException || $transport instanceof TransferException
            ? (method_exists($transport, 'getHandlerContext') ? $transport->getHandlerContext() : [])
            : [];

        $errno = isset($context['errno']) ? (int) $context['errno'] : null;
        $bytesSent = isset($context['request_size']) ? (int) $context['request_size'] : null;
        $message = "{$courier}: ".$e->getMessage();

        if ($errno !== null && in_array($errno, self::PRE_TRANSMISSION_CURL_ERRORS, true)) {
            return new self($message, false, 'connection_not_established', curlErrno: $errno, requestBytesSent: $bytesSent, previous: $e);
        }

        return new self($message, true, 'connection_interrupted', curlErrno: $errno, requestBytesSent: $bytesSent, previous: $e);
    }

    public static function fromHttpError(Response $response, string $message): self
    {
        $status = $response->status();

        if ($status >= 400 && $status < 500 && ! in_array($status, self::AMBIGUOUS_CLIENT_ERRORS, true)) {
            return new self($message, false, 'rejected', $status);
        }

        return new self($message, true, $status >= 500 ? 'server_error' : 'ambiguous_http_status', $status);
    }

    public function isOutcomeUnknown(): bool
    {
        return $this->outcomeUnknown;
    }
}
