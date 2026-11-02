<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\NewsletterController;
use App\Jobs\SendNewsletterChunk;
use App\Mail\NewsletterCustomMail;
use App\Models\Admin;
use App\Models\NewsletterSubscriber;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\Address;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = createAdmin();

    NewsletterSubscriber::create([
        'email' => 'subscriber@example.com',
        'token' => 'test-token',
        'is_active' => true,
        'subscribed_at' => now(),
    ]);
});

test('custom newsletter body strips malicious HTML before sending', function () {
    Mail::fake();
    actingAs($this->admin, 'admin');

    $maliciousBody = '<p>Hello</p><script>alert(1)</script><img src="x" onerror="alert(1)">';

    $response = $this->post(route('admin.newsletter.send'), [
        'type' => 'custom',
        'subject' => 'Test subject',
        'body' => $maliciousBody,
    ]);

    $response->assertRedirect();

    Mail::assertSent(NewsletterCustomMail::class, function (NewsletterCustomMail $mail) {
        return ! str_contains($mail->body, '<script')
            && ! str_contains($mail->body, 'onerror')
            && str_contains($mail->body, 'Hello');
    });
});

test('custom newsletter body preserves legitimate formatting supported by the current sanitizer', function () {
    Mail::fake();
    actingAs($this->admin, 'admin');

    $legitimateBody = '<p>Check out our <b>new</b> <a href="https://example.com">collection</a>.</p>';

    $this->post(route('admin.newsletter.send'), [
        'type' => 'custom',
        'subject' => 'Test subject',
        'body' => $legitimateBody,
    ]);

    Mail::assertSent(NewsletterCustomMail::class, function (NewsletterCustomMail $mail) {
        return str_contains($mail->body, '<b>new</b>')
            && str_contains($mail->body, 'href="https://example.com"')
            && str_contains($mail->body, 'collection');
    });
});

/*
 * FSA-13: sending is queued in chunks of RECIPIENTS_PER_MESSAGE BCC
 * recipients (one batch per send), each subscriber at most once, and the
 * send's uploads are deleted only after every chunk has been delivered.
 */

function addNewsletterSubscribers(int $count, string $prefix = 'reader'): void
{
    foreach (range(1, $count) as $i) {
        NewsletterSubscriber::create([
            'email' => "{$prefix}{$i}@example.com",
            'token' => "{$prefix}-token-{$i}",
            'is_active' => true,
            'subscribed_at' => now(),
        ]);
    }
}

/**
 * @return list<list<string>> BCC addresses of every message the array mailer delivered
 */
function deliveredNewsletterBccs(): array
{
    return app('mailer')->getSymfonyTransport()->messages()
        ->map(fn ($sent) => array_map(fn (Address $address) => $address->getAddress(), $sent->getOriginalMessage()->getBcc()))
        ->values()
        ->all();
}

/**
 * Runs every currently available job in-process. `--memory` is raised
 * because the worker otherwise stops after one job once the test process
 * itself is past its default 128 MB (as it is late in a full-suite run).
 */
function drainNewsletterQueue(): void
{
    Artisan::call('queue:work', ['connection' => 'database', '--stop-when-empty' => true, '--sleep' => 0, '--memory' => 4096]);
}

test('sending queues one batch of chunked jobs instead of mailing synchronously', function () {
    Mail::fake();
    Bus::fake();
    addNewsletterSubscribers(120);

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.newsletter.send'), ['type' => 'custom', 'subject' => 'Weekly', 'body' => '<p>Hi</p>'])
        ->assertRedirect(route('admin.newsletter.index'))
        ->assertSessionHas('success');

    Mail::assertNothingSent();
    Bus::assertBatched(function (PendingBatch $batch): bool {
        $sizes = $batch->jobs->map(fn (SendNewsletterChunk $job) => count($job->emails))->all();

        return str_starts_with($batch->name, NewsletterController::BATCH_NAME_PREFIX)
            && $batch->allowsFailures()
            && $sizes === [50, 50, 21];
    });
});

test('each active subscriber gets exactly one copy, in messages of at most 50 recipients', function () {
    Mail::fake();
    addNewsletterSubscribers(120);
    NewsletterSubscriber::create(['email' => 'READER1@example.com', 'token' => 'dup', 'is_active' => true, 'subscribed_at' => now()]);
    NewsletterSubscriber::create(['email' => 'gone@example.com', 'token' => 'gone', 'is_active' => false, 'subscribed_at' => now()]);

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.newsletter.send'), ['type' => 'custom', 'subject' => 'Weekly', 'body' => '<p>Hi</p>']);

    $bccs = Mail::sent(NewsletterCustomMail::class)
        ->map(fn (NewsletterCustomMail $mail) => array_column($mail->bcc, 'address'))
        ->values();
    $everyone = array_map('strtolower', $bccs->flatten()->all());

    expect($bccs)->toHaveCount(3)
        ->and($bccs->every(fn (array $bcc) => count($bcc) <= NewsletterController::RECIPIENTS_PER_MESSAGE))->toBeTrue()
        ->and($everyone)->toHaveCount(121)
        ->and(array_unique($everyone))->toHaveCount(121)
        ->and($everyone)->not->toContain('gone@example.com');
});

test('the subject, body and attachments reach every chunk, and the upload is deleted once all are delivered', function () {
    Mail::fake();
    Storage::fake('local');
    addNewsletterSubscribers(60);

    $this->actingAs($this->admin, 'admin')->post(route('admin.newsletter.send'), [
        'type' => 'custom',
        'subject' => 'Spring catalogue',
        'body' => '<p>New season</p>',
        'attachments' => [UploadedFile::fake()->create('catalogue.pdf', 20, 'application/pdf')],
    ]);

    $sent = Mail::sent(NewsletterCustomMail::class);
    $path = $sent->first()->attachmentPaths[0];

    expect($sent)->toHaveCount(2)
        ->and($sent->every(fn (NewsletterCustomMail $mail) => $mail->emailSubject === 'Spring catalogue'
            && str_contains($mail->body, 'New season')
            && $mail->attachmentPaths === [$path]))->toBeTrue()
        ->and($path)->toStartWith(NewsletterController::ATTACHMENT_ROOT.'/');
    Storage::disk('local')->assertMissing($path);
    expect(Storage::disk('local')->directories(NewsletterController::ATTACHMENT_ROOT))->toBe([]);
});

test('a failed chunk keeps the attachments until its retry succeeds, without re-sending the delivered chunk', function () {
    config(['queue.default' => 'database', 'mail.default' => 'array']);
    Storage::fake('local');
    addNewsletterSubscribers(59);
    NewsletterSubscriber::where('email', 'reader59@example.com')->update(['email' => 'flaky@example.com']);

    $failing = true;
    Event::listen(MessageSending::class, function (MessageSending $event) use (&$failing): void {
        $bcc = array_map(fn (Address $address) => $address->getAddress(), $event->message->getBcc());

        if ($failing && in_array('flaky@example.com', $bcc, true)) {
            throw new RuntimeException('Simulated SMTP rejection.');
        }
    });

    $this->actingAs($this->admin, 'admin')->post(route('admin.newsletter.send'), [
        'type' => 'custom', 'subject' => 'Weekly', 'body' => '<p>Hi</p>',
        'attachments' => [UploadedFile::fake()->create('flyer.pdf', 10, 'application/pdf')],
    ]);
    [$directory] = Storage::disk('local')->directories(NewsletterController::ATTACHMENT_ROOT);

    // The job's own $tries (3, as app:drain-queue) and 60s backoff apply:
    // the bad chunk is released and retried twice before it really fails.
    foreach (range(1, (new SendNewsletterChunk([], ['type' => 'custom', 'subject' => '', 'body' => '', 'attachment_paths' => []]))->tries) as $attempt) {
        drainNewsletterQueue();
        $this->travel(61)->seconds();
    }

    expect(deliveredNewsletterBccs())->toHaveCount(1)
        ->and(DB::table('failed_jobs')->count())->toBe(1)
        ->and(Storage::disk('local')->directories(NewsletterController::ATTACHMENT_ROOT))->toBe([$directory]);

    $failing = false;
    Artisan::call('queue:retry', ['id' => ['all']]);
    drainNewsletterQueue();

    $everyone = collect(deliveredNewsletterBccs())->flatten();
    expect(deliveredNewsletterBccs())->toHaveCount(2)
        ->and($everyone)->toHaveCount(60)
        ->and($everyone->unique())->toHaveCount(60)
        ->and(Storage::disk('local')->directories(NewsletterController::ATTACHMENT_ROOT))->toBe([]);
});

test('a subscriber who unsubscribes after the send was queued is skipped', function () {
    config(['queue.default' => 'database', 'mail.default' => 'array']);
    addNewsletterSubscribers(3);

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.newsletter.send'), ['type' => 'custom', 'subject' => 'Weekly', 'body' => '<p>Hi</p>']);
    NewsletterSubscriber::where('email', 'reader2@example.com')->update(['is_active' => false]);

    drainNewsletterQueue();

    expect(collect(deliveredNewsletterBccs())->flatten()->all())->not->toContain('reader2@example.com')
        ->and(collect(deliveredNewsletterBccs())->flatten())->toHaveCount(3);
});

test('an admin without newsletter create permission cannot send', function () {
    Bus::fake();
    $limited = Admin::create([
        'name' => 'Limited', 'email' => 'limited@example.com', 'password' => bcrypt('password'),
        'permissions' => ['newsletter' => ['view']],
    ]);

    $this->actingAs($limited, 'admin')
        ->post(route('admin.newsletter.send'), ['type' => 'custom', 'subject' => 'Weekly', 'body' => '<p>Hi</p>'])
        ->assertForbidden();

    Bus::assertNothingBatched();
});
