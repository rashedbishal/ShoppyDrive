<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ReceivedSms;
use App\Services\SmsParserService;
use App\Support\SmsAdminNotifier;
use App\Support\SmsForwarderHeartbeat;
use App\Support\SmsForwarderToken;
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

    /**
     * FSA-19: a missing/empty server token (no Setting row configured yet)
     * must fail closed, never throw — SmsForwarderToken::current() always
     * returns a string, so hash_equals() never sees null. Same source as
     * the sms-forwarder* rate limiters (AppServiceProvider::configureRateLimiting()).
     */
    private function tokenIsValid(Request $request): bool
    {
        $token = $request->bearerToken();
        $expectedToken = SmsForwarderToken::current();

        return $expectedToken !== '' && $token && hash_equals($expectedToken, $token);
    }

    /**
     * OPS-005: a liveness-only ping from the forwarder app's foreground
     * service, sent every 1-2 minutes regardless of whether any SMS has
     * arrived — see SmsForwarderHeartbeat for how this is used.
     * Route: POST /api/sms/heartbeat
     */
    public function heartbeat(Request $request): JsonResponse
    {
        if (! $this->tokenIsValid($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        SmsForwarderHeartbeat::record();

        return response()->json(['status' => 'ok']);
    }

    public function receive(Request $request): JsonResponse
    {
        if (! $this->tokenIsValid($request)) {
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

        // PERF-006 (web push follow-up): replaces the admin layout's poll —
        // queued, so a slow/unreachable push service can never delay this
        // response to the forwarder app.
        SmsAdminNotifier::notifyNewSms($sms);

        return response()->json(['status' => 'ok']);
    }
}
