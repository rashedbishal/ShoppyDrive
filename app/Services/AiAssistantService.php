<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Support\AiAssistantConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Talks to Groq's OpenAI-compatible chat-completions API on the customer's
 * behalf, running whichever model Admin has chosen (AiAssistantConfig::model(),
 * one of CANDIDATE_MODELS, default Llama 3.1 8B Instant). The model is never
 * given database access — it can only ask for one of the three tools below,
 * each of which goes through AiAssistantTools and the exact same
 * visibility/ownership rules the rest of the storefront already enforces. A
 * reply the model tries to make about anything outside this store is
 * refused by the system instruction, not by code — if that ever proves
 * unreliable in practice, tighten the instruction rather than trying to
 * out-guess it here.
 *
 * Unlike Gemini's old stateful "Interactions" API (one `previous_interaction_id`,
 * conversation held server-side by Google), Groq's chat-completions endpoint
 * is stateless: the *entire* prior conversation has to be resent on every
 * turn, and AiAssistantController/AiAssistantPanel.tsx hold that history
 * client-side instead of an opaque id. MAX_HISTORY_MESSAGES bounds it here
 * too, defensively, since every message in it is billed as input tokens
 * again on every single turn regardless of what the client sends.
 */
class AiAssistantService
{
    private const ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';

    private const MODELS_ENDPOINT = 'https://api.groq.com/openai/v1/models';

    private const MAX_TOOL_ROUNDTRIPS = 4;

    /** @var list<int> Worth one retry: a rate limit or a transient overload, not a bad request or bad key. */
    private const RETRYABLE_STATUSES = [429, 503];

    private const RETRY_DELAY_SECONDS = 1;

    /** Prior turns kept when building the request — oldest trimmed first. */
    private const MAX_HISTORY_MESSAGES = 20;

    public function __construct(private readonly AiAssistantTools $tools) {}

    /**
     * @param  list<array{role: string, content: string}>  $history  Prior user/assistant turns, oldest first.
     * @return array{reply: string}
     */
    public function respond(string $message, array $history, ?User $authUser): array
    {
        $apiKey = AiAssistantConfig::apiKey();

        if ($apiKey === '') {
            return ['reply' => "Sorry, the assistant isn't set up yet."];
        }

        $messages = [
            ['role' => 'system', 'content' => $this->systemInstruction()],
            ...array_slice($history, -self::MAX_HISTORY_MESSAGES),
            ['role' => 'user', 'content' => $message],
        ];

        for ($round = 0; $round < self::MAX_TOOL_ROUNDTRIPS; $round++) {
            $response = $this->call($apiKey, $messages);

            if ($response === null) {
                return ['reply' => "Sorry, I'm having trouble right now — please try again in a moment."];
            }

            $choice = $response['choices'][0]['message'] ?? null;

            if (! is_array($choice)) {
                Log::warning('[AI assistant] Unexpected Groq response shape', ['response' => $response]);

                return ['reply' => "Sorry, I didn't catch that — could you rephrase?"];
            }

            $toolCalls = $choice['tool_calls'] ?? null;

            if (! is_array($toolCalls) || $toolCalls === []) {
                $text = is_string($choice['content'] ?? null) ? trim($choice['content']) : '';

                return ['reply' => $text !== '' ? $text : "Sorry, I didn't catch that — could you rephrase?"];
            }

            $messages[] = ['role' => 'assistant', 'content' => $choice['content'] ?? null, 'tool_calls' => $toolCalls];

            foreach ($toolCalls as $toolCall) {
                $name = (string) ($toolCall['function']['name'] ?? '');
                $arguments = json_decode((string) ($toolCall['function']['arguments'] ?? '{}'), true);
                $result = $this->runTool($name, is_array($arguments) ? $arguments : [], $authUser);

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => (string) ($toolCall['id'] ?? ''),
                    'content' => json_encode($result),
                ];
            }
        }

        return ['reply' => "Sorry, that's taking longer than expected — please try asking in a simpler way."];
    }

    /**
     * For Admin's model dropdown: CANDIDATE_MODELS narrowed to whatever
     * Groq's account-level catalog (`/v1/models`) actually reports as
     * available right now, so a retired or renamed model id can't linger
     * as a choice — the exact failure mode a hardcoded model name hit
     * twice already. Falls back to the full CANDIDATE_MODELS list (never
     * an empty one) when there's no key yet or the lookup fails for any
     * reason, same fail-safe spirit as the rest of this class.
     *
     * @return array<string, string> model id => display label
     */
    public function availableModels(): array
    {
        $apiKey = AiAssistantConfig::apiKey();

        if ($apiKey === '') {
            return AiAssistantConfig::CANDIDATE_MODELS;
        }

        try {
            $response = Http::withToken($apiKey)
                ->withOptions(['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]])
                ->timeout(5)->get(self::MODELS_ENDPOINT);
        } catch (ConnectionException) {
            return AiAssistantConfig::CANDIDATE_MODELS;
        }

        if (! $response->successful()) {
            return AiAssistantConfig::CANDIDATE_MODELS;
        }

        $availableIds = collect($response->json('data', []))->pluck('id')->all();

        $filtered = array_intersect_key(AiAssistantConfig::CANDIDATE_MODELS, array_flip($availableIds));

        return $filtered !== [] ? $filtered : AiAssistantConfig::CANDIDATE_MODELS;
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
     * @param  list<array<string, mixed>>  $messages
     * @return array<string, mixed>|null
     */
    private function call(string $apiKey, array $messages): ?array
    {
        $payload = [
            'model' => AiAssistantConfig::model(),
            'messages' => $messages,
            'tools' => $this->toolDeclarations(),
            'tool_choice' => 'auto',
        ];

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $response = Http::withToken($apiKey)
                    // Groq's API is single-stack as far as we've seen, but this
                    // is a cheap, harmless defensive default against the same
                    // dual-stack-host-hangs-on-a-dead-IPv6-route failure mode
                    // that previously hit the old Gemini integration.
                    ->withOptions(['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]])
                    ->timeout(20)->post(self::ENDPOINT, $payload);
            } catch (ConnectionException $e) {
                // Already spent the full timeout budget getting here —
                // retrying a hung connection risks the web server's own
                // execution time limit, so this one isn't retried.
                Log::warning('[AI assistant] Could not reach Groq', [
                    'message' => $e->getMessage(),
                ]);

                return null;
            }

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('[AI assistant] Groq request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'attempt' => $attempt,
            ]);

            // A 429 (rate limit) or 503 (overload) can clear on its own; a
            // bad key, a malformed request, or an exhausted quota won't
            // succeed on a second try, so only these are worth retrying,
            // once.
            if (! in_array($response->status(), self::RETRYABLE_STATUSES, true) || $attempt === 2) {
                return null;
            }

            Sleep::for(self::RETRY_DELAY_SECONDS)->seconds();
        }

        return null;
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
                'function' => [
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
            ],
            [
                'type' => 'function',
                'function' => [
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
            ],
            [
                'type' => 'function',
                'function' => [
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
            ],
        ];
    }
}
