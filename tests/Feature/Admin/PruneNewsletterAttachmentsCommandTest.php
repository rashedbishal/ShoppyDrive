<?php

use App\Http\Controllers\Admin\NewsletterController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
 * FSA-13 backstop: newsletter uploads left after their send are removed,
 * but never while the send's batch may still need them.
 */

beforeEach(function () {
    Storage::fake('local');
});

/**
 * Stores one upload for a send, last modified $ageInSeconds ago, and returns
 * the send's directory.
 */
function newsletterUpload(int $ageInSeconds, ?string $sendId = null): string
{
    $directory = NewsletterController::ATTACHMENT_ROOT.'/'.($sendId ?? (string) Str::uuid());
    $disk = Storage::disk('local');
    $disk->put("{$directory}/flyer.pdf", 'pdf');
    touch($disk->path("{$directory}/flyer.pdf"), now()->getTimestamp() - $ageInSeconds);

    return $directory;
}

function newsletterBatchRow(string $directory, bool $finished, int $failedJobs = 0): void
{
    DB::table('job_batches')->insert([
        'id' => (string) Str::uuid(),
        'name' => NewsletterController::BATCH_NAME_PREFIX.basename($directory),
        'total_jobs' => 2,
        'pending_jobs' => $finished ? 0 : 1,
        'failed_jobs' => $failedJobs,
        'failed_job_ids' => '[]',
        'options' => '',
        'created_at' => now()->getTimestamp(),
        'finished_at' => $finished ? now()->getTimestamp() : null,
    ]);
}

test('a finished send\'s leftover upload is removed', function () {
    $directory = newsletterUpload(60);
    newsletterBatchRow($directory, finished: true);

    $this->artisan('newsletter:prune-attachments')->assertSuccessful();

    expect(Storage::disk('local')->exists($directory))->toBeFalse();
});

test('a send still in progress keeps its upload, even with a failed chunk awaiting retry', function () {
    $directory = newsletterUpload(10 * 86400);
    newsletterBatchRow($directory, finished: false, failedJobs: 1);

    $this->artisan('newsletter:prune-attachments')->assertSuccessful();

    Storage::disk('local')->assertExists("{$directory}/flyer.pdf");
});

test('an unfinished send abandoned for over 30 days is removed', function () {
    $directory = newsletterUpload(31 * 86400);
    newsletterBatchRow($directory, finished: false, failedJobs: 1);

    $this->artisan('newsletter:prune-attachments')->assertSuccessful();

    expect(Storage::disk('local')->exists($directory))->toBeFalse();
});

test('an upload never queued is removed after a day but kept before that', function () {
    $old = newsletterUpload(25 * 3600);
    $recent = newsletterUpload(3600);

    $this->artisan('newsletter:prune-attachments')->assertSuccessful();

    expect(Storage::disk('local')->exists($old))->toBeFalse();
    Storage::disk('local')->assertExists("{$recent}/flyer.pdf");
});

test('legacy tmp uploads from the old synchronous sender are removed after a day', function () {
    $disk = Storage::disk('local');
    $tmp = NewsletterController::ATTACHMENT_ROOT.'/tmp';
    $disk->put("{$tmp}/old.pdf", 'pdf');
    touch($disk->path("{$tmp}/old.pdf"), now()->subDays(2)->getTimestamp());
    $disk->put("{$tmp}/fresh.pdf", 'pdf');

    $this->artisan('newsletter:prune-attachments')->assertSuccessful();

    $disk->assertMissing("{$tmp}/old.pdf");
    $disk->assertExists("{$tmp}/fresh.pdf");
});
