<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ReceivedSms;
use App\Services\SmsParserService;
use App\Support\SmsRegex;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SmsTestController extends Controller
{
    public function __construct(private readonly SmsParserService $parser) {}

    private function parseReceivedAt(mixed $value): CarbonInterface
    {
        if ($value === null) {
            return now();
        }

        if (is_numeric($value)) {
            $ts = (int) $value;
            // Android sends milliseconds; anything > 10 digits is ms
            if ($ts > 9_999_999_999) {
                $ts = intdiv($ts, 1000);
            }

            return Carbon::createFromTimestamp($ts);
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return now();
        }
    }

    public function receive(Request $request): JsonResponse
    {
        $token = $request->bearerToken();

        // FSA-19: config('services.sms_forwarder.token') can be null (e.g. a
        // stale config:cache predating this key), and hash_equals() throws
        // a TypeError under strict_types for a null argument instead of
        // just failing the comparison — a missing/empty server token must
        // fail closed into the same 401 as a wrong token, never a 500.
        // Same cast-and-guard shape as the sms-forwarder rate limiter below
        // (AppServiceProvider::configureRateLimiting()).
        $expectedToken = (string) config('services.sms_forwarder.token');

        if ($expectedToken === '' || ! $token || ! hash_equals($expectedToken, $token)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $content = (string) $request->input('content', '');

        // Acknowledged, because the forwarder retries anything else, and no
        // real payment SMS is this long.
        if (mb_strlen($content) > SmsRegex::MAX_CONTENT_LENGTH) {
            Log::warning('[SMS] Oversized message ignored', ['length' => mb_strlen($content)]);

            return response()->json(['status' => 'ignored']);
        }

        $sender = (string) $request->input('from', '');
        $parsed = $this->parser->parse($content, $sender);

        try {
            $sms = ReceivedSms::create([
                'sender' => $sender,
                'provider' => $parsed['provider'],
                'content' => $content,
                'received_at' => $this->parseReceivedAt($request->input('received_at')),
                'parsed_transaction_id' => $parsed['transaction_id'],
                'parsed_amount' => $parsed['amount'],
            ]);
        } catch (QueryException $e) {
            // A (provider, parsed_transaction_id) unique constraint protects
            // against the same real transaction being claimable twice. The
            // forwarder app retries on any network hiccup, so a duplicate
            // delivery of the same SMS is expected, not an error — ignore it.
            // SQLSTATE 23000 covers any integrity-constraint violation, not
            // just this one, so confirm a row for this exact key actually
            // exists before swallowing the error — otherwise rethrow so an
            // unrelated failure (e.g. a NOT NULL violation) surfaces as one.
            $isDuplicateKey = $parsed['transaction_id'] !== null
                && $e->getCode() === '23000'
                && ReceivedSms::where('provider', $parsed['provider'])
                    ->where('parsed_transaction_id', $parsed['transaction_id'])
                    ->exists();

            if (! $isDuplicateKey) {
                throw $e;
            }

            Log::info('SMS received (duplicate delivery ignored)', [
                'provider' => $parsed['provider'],
                'transaction_id' => $parsed['transaction_id'],
            ]);

            return response()->json(['status' => 'ok']);
        }

        Log::info('SMS received', [
            'id' => $sms->id,
            'transaction_id' => $sms->parsed_transaction_id,
            'amount' => $sms->parsed_amount,
        ]);

        return response()->json(['status' => 'ok']);
    }
}
