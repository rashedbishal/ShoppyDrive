<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ReceivedSms;
use App\Services\SmsParserService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SmsTestController extends Controller
{
    public function __construct(private readonly SmsParserService $parser) {}

    private function parseReceivedAt(mixed $value): Carbon
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

        if (! $token || ! hash_equals(config('services.sms_forwarder.token'), $token)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $content = (string) $request->input('content', '');
        $parsed = $this->parser->parse($content);

        $sms = ReceivedSms::create([
            'sender' => $request->input('from', ''),
            'provider' => $parsed['provider'],
            'content' => $content,
            'received_at' => $this->parseReceivedAt($request->input('received_at')),
            'parsed_transaction_id' => $parsed['transaction_id'],
            'parsed_amount' => $parsed['amount'],
        ]);

        Log::info('SMS received', [
            'id' => $sms->id,
            'from' => $sms->sender,
            'transaction_id' => $sms->parsed_transaction_id,
            'amount' => $sms->parsed_amount,
        ]);

        return response()->json(['status' => 'ok']);
    }
}
