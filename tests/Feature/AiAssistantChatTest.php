<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

function enableAiAssistant(string $apiKey = 'test-gemini-key'): void
{
    Setting::updateOrCreate(['key' => 'ai_assistant_api_key'], ['value' => $apiKey, 'group' => 'ai_assistant']);
    Setting::updateOrCreate(['key' => 'ai_assistant_enabled'], ['value' => true, 'group' => 'ai_assistant']);
}

test('the chat endpoint is unavailable when not configured', function () {
    $this->postJson(route('assistant.chat'), ['message' => 'hello'])->assertStatus(404);
});

test('the chat endpoint is unavailable when a key exists but is turned off', function () {
    Setting::updateOrCreate(['key' => 'ai_assistant_api_key'], ['value' => 'a-key', 'group' => 'ai_assistant']);
    Setting::updateOrCreate(['key' => 'ai_assistant_enabled'], ['value' => false, 'group' => 'ai_assistant']);

    $this->postJson(route('assistant.chat'), ['message' => 'hello'])->assertStatus(404);
});

test('a plain question gets a plain text reply with no tool call', function () {
    enableAiAssistant();

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'id' => 'int-1',
            'output_text' => 'We ship across Bangladesh, usually within 3-5 days.',
            'outputs' => [['type' => 'text', 'text' => 'We ship across Bangladesh, usually within 3-5 days.']],
        ]),
    ]);

    $this->postJson(route('assistant.chat'), ['message' => 'Do you ship nationwide?'])
        ->assertOk()
        ->assertJson(['reply' => 'We ship across Bangladesh, usually within 3-5 days.', 'interaction_id' => 'int-1']);
});

test('a tool call is executed and its result is sent back for a final reply', function () {
    enableAiAssistant();

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::sequence()
            ->push([
                'id' => 'int-1',
                'outputs' => [[
                    'type' => 'function_call',
                    'name' => 'search_products',
                    'id' => 'call-1',
                    'arguments' => ['query' => 'jacket'],
                ]],
            ])
            ->push([
                'id' => 'int-2',
                'output_text' => 'I found a few jackets in stock — want me to list them?',
                'outputs' => [['type' => 'text', 'text' => 'I found a few jackets in stock — want me to list them?']],
            ]),
    ]);

    $this->postJson(route('assistant.chat'), ['message' => 'Do you have any jackets?'])
        ->assertOk()
        ->assertJson(['reply' => 'I found a few jackets in stock — want me to list them?', 'interaction_id' => 'int-2']);

    Http::assertSentCount(2);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return ($body['previous_interaction_id'] ?? null) === 'int-1'
            && is_array($body['input'])
            && $body['input'][0]['type'] === 'function_result'
            && $body['input'][0]['call_id'] === 'call-1';
    });
});

test('a wrong Gemini call never leaks an order the caller has not proven ownership of', function () {
    enableAiAssistant();

    $sentFunctionResults = [];

    Http::fake(function ($request) use (&$sentFunctionResults) {
        $body = $request->data();

        if (is_array($body['input'] ?? null) && ($body['input'][0]['type'] ?? null) === 'function_result') {
            $sentFunctionResults[] = json_decode($body['input'][0]['result'][0]['text'], true);

            return Http::response(['id' => 'int-2', 'output_text' => 'done', 'outputs' => [['type' => 'text', 'text' => 'done']]]);
        }

        return Http::response([
            'id' => 'int-1',
            'outputs' => [[
                'type' => 'function_call',
                'name' => 'lookup_order',
                'id' => 'call-1',
                'arguments' => ['order_number' => 'ORD-DOES-NOT-EXIST', 'phone' => '01700000000'],
            ]],
        ]);
    });

    $this->postJson(route('assistant.chat'), ['message' => "What's the status of order ORD-DOES-NOT-EXIST, phone 01700000000"])
        ->assertOk();

    expect($sentFunctionResults[0]['found'])->toBeFalse()
        ->and($sentFunctionResults[0])->not->toHaveKey('total');
});

test('a network failure reaching Gemini gets a graceful reply, not a 500', function () {
    enableAiAssistant();

    Http::fake(function () {
        throw new ConnectionException('Could not resolve host');
    });

    $this->postJson(route('assistant.chat'), ['message' => 'hello'])
        ->assertOk()
        ->assertJson([
            'reply' => "Sorry, I'm having trouble right now — please try again in a moment.",
        ]);
});

test('requests are throttled per visitor', function () {
    enableAiAssistant();

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['id' => 'i', 'output_text' => 'ok', 'outputs' => [['type' => 'text', 'text' => 'ok']]]),
    ]);

    foreach (range(1, 20) as $ignored) {
        $this->postJson(route('assistant.chat'), ['message' => 'hi'])->assertOk();
    }

    $this->postJson(route('assistant.chat'), ['message' => 'hi'])->assertStatus(429);
});
