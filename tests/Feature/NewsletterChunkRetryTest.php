<?php

declare(strict_types=1);

/*
 * BE-012, end to end: a newsletter chunk that exhausts its tries lands in
 * Failed Jobs without cancelling the other chunks, and retrying it from the
 * admin panel really delivers it (no per-recipient state is needed — each
 * chunk is one BCC message, so it either went out whole or not at all).
 */

use App\Console\Commands\DrainQueueCommand;
use App\Jobs\SendNewsletterChunk;
use App\Mail\NewsletterCustomMail;
use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travel;

uses(RefreshDatabase::class);

/**
 * `--memory` is raised because queue:work stops after the current job once the
 * process passes its default 128 MB, and under the full suite this single
 * PHPUnit process is already well past that, so each call would handle only
 * one job instead of draining the queue.
 */
function runNewsletterQueueOnce(): void
{
    Artisan::call('queue:work', [
        'connection' => 'database', '--queue' => 'default,'.DrainQueueCommand::NEWSLETTER_QUEUE,
        '--stop-when-empty' => true, '--tries' => 3, '--sleep' => 0, '--memory' => 4096,
    ]);
}

test('a chunk that exhausts its tries fails alone, can be retried from admin, and is then delivered', function () {
    config(['queue.default' => 'database']);
    Mail::fake();
    Storage::fake('local');

    $failingEmails = ['a1@example.com', 'a2@example.com'];
    $healthyEmails = ['b1@example.com', 'b2@example.com'];
    foreach ([...$failingEmails, ...$healthyEmails] as $email) {
        NewsletterSubscriber::create(['email' => $email, 'is_active' => true, 'token' => Str::random(40), 'subscribed_at' => now()]);
    }

    $missingAttachment = 'newsletter-attachments/send-1/flyer.pdf';
    $newsletter = fn (array $paths): array => ['type' => 'custom', 'subject' => 'Sale', 'body' => '<p>Hi</p>', 'attachment_paths' => $paths];

    $batch = Bus::batch([
        new SendNewsletterChunk($failingEmails, $newsletter([$missingAttachment])),
        new SendNewsletterChunk($healthyEmails, $newsletter([])),
    ])->onQueue(DrainQueueCommand::NEWSLETTER_QUEUE)->allowFailures()->dispatch();

    // Three tries, 60s apart (the job's own backoff).
    foreach ([1, 2, 3] as $attempt) {
        runNewsletterQueueOnce();
        travel(2)->minutes();
    }

    // The healthy chunk went out; the failing one is in Failed Jobs and the batch was not cancelled.
    Mail::assertSent(NewsletterCustomMail::class, fn ($mail) => $mail->hasBcc('b1@example.com') && $mail->hasBcc('b2@example.com'));
    Mail::assertSent(NewsletterCustomMail::class, 1);
    expect(DB::table('failed_jobs')->count())->toBe(1)
        ->and($batch->fresh()->cancelled())->toBeFalse();

    $row = DB::table('failed_jobs')->first();

    // Admin can see it as retryable, with the duplicate warning, and retry it.
    $admin = createMotherAdmin();
    actingAs($admin, 'admin')->get(route('admin.failed-jobs.index'))
        ->assertInertia(fn ($page) => $page
            ->where('failedJobs.data.0.can_retry', true)
            ->where('failedJobs.data.0.retry_warning', fn ($warning) => str_contains((string) $warning, 'twice')));

    // The cause gets fixed (the attachment is back), then staff retry it.
    Storage::disk('local')->put($missingAttachment, 'pdf');
    actingAs($admin, 'admin')->post(route('admin.failed-jobs.retry', $row->uuid), ['confirmed' => true])
        ->assertSessionHas('success');

    runNewsletterQueueOnce();

    Mail::assertSent(NewsletterCustomMail::class, 2);
    Mail::assertSent(NewsletterCustomMail::class, fn ($mail) => $mail->hasBcc('a1@example.com') && $mail->hasBcc('a2@example.com'));
    expect(DB::table('failed_jobs')->count())->toBe(0);
});
