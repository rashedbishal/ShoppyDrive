<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Support\AiAssistantConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talks to Google's Gemini API on the customer's behalf. The model is
 * never given database access — it can only ask for one of the three
 * tools below, each of which goes through AiAssistantTools and the exact
 * same visibility/ownership rules the rest of the storefront already
 * enforces. A reply the model tries to make about anything outside this
 * store is refused by the system instruction, not by code — if that ever
 * proves unreliable in practice, tighten the instruction rather than
 * trying to out-guess it here.
 *
 * Built against Google's "Interactions" API (v1beta), which replaced the
 * older generateContent endpoint — see AiAssistantConfig for where the
 * API key lives. The exact response field names below (`outputs` vs
 * `steps`, etc.) were pieced together from Google's docs rather than a
 * live test call; parseOutputs() checks a couple of plausible shapes for
 * exactly that reason. If a real conversation ever logs an "unexpected
 * Gemini response shape" warning, that's the place to adjust.
 */
class AiAssistantService
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/interactions';

    private const MODEL = 'gemini-3.8-flash';

    private const MAX_TOOL_ROUNDTRIPS = 4;

    public function __construct(private readonly AiAssistantTools $tools) {}

    /**
     * @return array{reply: string, interaction_id: ?string}
     */
    public function respond(string $message, ?string $previousInteractionId, ?User $authUser): array
    {
        $apiKey = AiAssistantConfig::apiKey();

        if ($apiKey === '') {
            return ['reply' => "Sorry, the assistant isn't set up yet.", 'interaction_id' => null];
        }

        $input = $message;
        $interactionId = $previousInteractionId;

        for ($round = 0; $round < self::MAX_TOOL_ROUNDTRIPS; $round++) {
            $response = $this->call($apiKey, $input, $interactionId);

            if ($response === null) {
                return ['reply' => "Sorry, I'm having trouble right now — please try again in a moment.", 'interaction_id' => $previousInteractionId];
            }

            $interactionId = $response['id'] ?? $interactionId;
            $outputs = $this->parseOutputs($response);

            $functionCall = collect($outputs)->first(fn ($o) => ($o['type'] ?? null) === 'function_call');

            if ($functionCall === null) {
                $text = $this->extractText($response, $outputs);

                return ['reply' => $text !== '' ? $text : "Sorry, I didn't catch that — could you rephrase?", 'interaction_id' => $interactionId];
            }

            $result = $this->runTool($functionCall['name'] ?? '', $functionCall['arguments'] ?? [], $authUser);

            $input = [[
                'type' => 'function_result',
                'name' => $functionCall['name'] ?? '',
                'call_id' => $functionCall['id'] ?? ($functionCall['call_id'] ?? ''),
                'result' => [['type' => 'text', 'text' => json_encode($result)]],
            ]];
        }

        return ['reply' => "Sorry, that's taking longer than expected — please try asking in a simpler way.", 'interaction_id' => $interactionId];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function runTool(string $name, array $arguments, ?User $authUser): array
    {
        try {
            return match ($name) {
                'search_products' => $this->tools->searchProducts((string) ($arguments['query'] ?? '')),
                'check_product_availability' => $this->tools->checkProductAvailability((string) ($arguments['product_name'] ?? '')),
                'lookup_order' => $this->tools->lookupOrder(
                    $authUser,
                    $arguments['order_number'] ?? null,
                    $arguments['email'] ?? null,
                    $arguments['phone'] ?? null,
                ),
                default => ['found' => false, 'message' => 'Unknown tool.'],
            };
        } catch (\Throwable $e) {
            report($e);

            return ['found' => false, 'message' => 'Something went wrong looking that up.'];
        }
    }

    /**
     * @param  string|array<int, array<string, mixed>>  $input
     * @return array<string, mixed>|null
     */
    private function call(string $apiKey, string|array $input, ?string $previousInteractionId): ?array
    {
        $payload = [
            'model' => self::MODEL,
            'system_instruction' => $this->systemInstruction(),
            'tools' => $this->toolDeclarations(),
            'input' => $input,
        ];

        if ($previousInteractionId !== null) {
            $payload['previous_interaction_id'] = $previousInteractionId;
        }

        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(20)->post(self::ENDPOINT, $payload);
        } catch (ConnectionException $e) {
            Log::warning('[AI assistant] Could not reach Gemini', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        if ($response->failed()) {
            Log::warning('[AI assistant] Gemini request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $response
     * @return list<array<string, mixed>>
     */
    private function parseOutputs(array $response): array
    {
        $outputs = $response['outputs'] ?? $response['steps'] ?? null;

        if (! is_array($outputs)) {
            Log::warning('[AI assistant] Unexpected Gemini response shape', ['response' => $response]);

            return [];
        }

        return $outputs;
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  list<array<string, mixed>>  $outputs
     */
    private function extractText(array $response, array $outputs): string
    {
        if (is_string($response['output_text'] ?? null)) {
            return $response['output_text'];
        }

        $textOutput = collect($outputs)->first(fn ($o) => ($o['type'] ?? null) === 'text' || ($o['type'] ?? null) === 'message');

        return (string) ($textOutput['text'] ?? $textOutput['content'] ?? '');
    }

    private function systemInstruction(): string
    {
        return <<<'PROMPT'
            You are the shopping assistant for ShoppyDrive, a Bangladesh-based
            online store. Only discuss this store: its products, prices, stock,
            orders, shipping and policies. If asked anything unrelated, politely
            decline and steer back to how you can help with the store — never
            answer the unrelated question itself.

            Never invent product details, prices, stock levels, or order
            information — only state what a tool call actually returned. If a
            tool reports something was not found or did not match, say so
            plainly rather than guessing.

            When looking up an order for someone who is not logged in, you must
            have both an order number and the email or phone used at checkout
            before calling lookup_order — ask for whichever is missing first.
            Never reveal any order's details unless the tool call confirms a
            match.

            Keep replies short and friendly, suited to a chat widget.
            PROMPT;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function toolDeclarations(): array
    {
        return [
            [
                'type' => 'function',
                'name' => 'search_products',
                'description' => 'Search the store\'s products by name or description keywords.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Search keywords, e.g. "red shoes"'],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'type' => 'function',
                'name' => 'check_product_availability',
                'description' => 'Check the current price and stock for one specific product by name.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'product_name' => ['type' => 'string', 'description' => 'The exact or near-exact product name'],
                    ],
                    'required' => ['product_name'],
                ],
            ],
            [
                'type' => 'function',
                'name' => 'lookup_order',
                'description' => 'Look up the status of a customer order. For a guest, order_number plus email or phone are all required before calling this.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'order_number' => ['type' => 'string', 'description' => 'e.g. ORD-20261107-AB12CD'],
                        'email' => ['type' => 'string', 'description' => 'Email used at checkout, if the customer gave one instead of a phone'],
                        'phone' => ['type' => 'string', 'description' => 'Phone used at checkout, if the customer gave one instead of an email'],
                    ],
                    'required' => [],
                ],
            ],
        ];
    }
}
