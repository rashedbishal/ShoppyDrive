<?php

declare(strict_types=1);

/*
 * FE-010: when a page crashes in the browser, the error boundary reports it
 * here so it reaches laravel.log. Only the shortened error, the page path
 * (never the query string) and the browser are logged.
 */

use Illuminate\Support\Facades\Log;

test('a browser crash report is written to the log without the query string', function () {
    Log::spy();

    $this->postJson(route('client-errors.store'), [
        'message' => "Cannot read properties of undefined (reading 'price')",
        'path' => '/checkout?coupon=SECRET&token=abc',
        'component_stack' => "\n    at OrderSummary\n    at Checkout",
    ], ['User-Agent' => 'Mozilla/5.0 Test'])->assertNoContent();

    Log::shouldHaveReceived('warning')->once()->with('Browser page crash', Mockery::on(fn (array $context) => $context['message'] === "Cannot read properties of undefined (reading 'price')"
        && $context['path'] === '/checkout'
        && str_contains($context['component_stack'], 'OrderSummary')
        && $context['user_agent'] === 'Mozilla/5.0 Test'));
});

test('very long reports are shortened before logging', function () {
    Log::spy();

    $this->postJson(route('client-errors.store'), ['message' => str_repeat('x', 1500), 'path' => '/'])->assertNoContent();

    Log::shouldHaveReceived('warning')->once()->with('Browser page crash', Mockery::on(fn (array $context) => mb_strlen($context['message']) <= 503));
});

test('a report without a message is refused and nothing is logged', function () {
    Log::spy();

    $this->postJson(route('client-errors.store'), ['path' => '/'])->assertUnprocessable();

    Log::shouldNotHaveReceived('warning');
});

test('one visitor cannot flood the log', function () {
    foreach (range(1, 10) as $ignored) {
        $this->postJson(route('client-errors.store'), ['message' => 'boom', 'path' => '/'])->assertNoContent();
    }

    $this->postJson(route('client-errors.store'), ['message' => 'boom', 'path' => '/'])->assertStatus(429);
});
