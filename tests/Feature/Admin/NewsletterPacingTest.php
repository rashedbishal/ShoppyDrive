<?php

declare(strict_types=1);

/*
 * OPS-004: a big newsletter could use up the mail account's sending limit
 * and delay order mail behind it. Chunks now go on their own queue, which
 * the worker reads after the default one, 5 minutes apart.
 */

use App\Console\Commands\DrainQueueCommand;
use App\Mail\NewsletterCustomMail;
use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function queueNewsletterTo(int $subscribers): void
{
    config(['queue.default' => 'database']);
    foreach (range(1, $subscribers) as $n) {
        NewsletterSubscriber::create(['email' => "reader{$n}@example.com", 'is_active' => true, 'token' => "token-{$n}", 'subscribed_at' => now()]);
    }

    actingAs(createMotherAdmin(), 'admin')->post(route('admin.newsletter.send'), ['type' => 'custom', 'subject' => 'Weekly', 'body' => '<p>Hi</p>']);
}

test('newsletter chunks go on their own queue, 5 minutes apart', function () {
    $this->freezeSecond();
    queueNewsletterTo(120);

    $jobs = DB::table('jobs')->orderBy('available_at')->get();

    expect($jobs)->toHaveCount(3)
        ->and($jobs->pluck('queue')->unique()->all())->toBe([DrainQueueCommand::NEWSLETTER_QUEUE])
        ->and($jobs->pluck('available_at')->map(fn ($at) => (int) $at - now()->getTimestamp())->all())->toBe([0, 300, 600]);
});

test('the admin is told how long the send will take', function () {
    queueNewsletterTo(120);

    expect(session('success'))->toContain('every 5 minutes')->toContain('about 10 minutes');
});

test('a delayed newsletter chunk never raises the stalled queue warning', function () {
    queueNewsletterTo(120);

    $this->travel(11)->minutes();

    expect(DrainQueueCommand::oldestPendingJobAgeSeconds())->toBeNull();
});

test('newsletter emails carry a List-Unsubscribe header', function () {
    $mail = new NewsletterCustomMail('Weekly', '<p>Hi</p>');

    expect($mail->headers()->text)->toBe(['List-Unsubscribe' => '<'.route('newsletter.manage').'>']);
});
