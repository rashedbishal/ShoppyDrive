<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\NewsletterCustomMail;
use App\Mail\NewsletterProductMail;
use App\Models\NewsletterSubscriber;
use App\Models\Product;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * FSA-13: sends one newsletter to one chunk of subscribers as a single BCC
 * message, so a retry can only repeat a message that never went out (the
 * send is the job's last step) and never reaches anyone in another chunk.
 * Dispatched in a batch by Admin\NewsletterController::send(); the batch's
 * `then` callback deletes the shared attachments once every chunk has been
 * delivered (see PruneNewsletterAttachments for the backstop).
 */
class SendNewsletterChunk implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * Matches `app:drain-queue`'s `--tries=3`.
     */
    public int $tries = 3;

    public int $backoff = 60;

    /**
     * @param  list<string>  $emails
     * @param  array{type: 'custom', subject: string, body: string, attachment_paths: list<string>}|array{type: 'product_promotion', subject: string, product_id: int, selected_image: string|null}  $newsletter
     */
    public function __construct(
        public readonly array $emails,
        public readonly array $newsletter,
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        // Respect anyone who unsubscribed after the send was queued.
        $recipients = NewsletterSubscriber::query()
            ->whereIn('email', $this->emails)
            ->where('is_active', true)
            ->pluck('email')
            ->all();

        if ($recipients === []) {
            return;
        }

        Mail::to(config('mail.from.address'))
            ->bcc($recipients)
            ->send($this->mailable());
    }

    private function mailable(): Mailable
    {
        if ($this->newsletter['type'] === 'custom') {
            $paths = $this->newsletter['attachment_paths'];

            // Fail (and stay retryable) rather than send a newsletter that
            // silently lost its attachments.
            foreach ($paths as $path) {
                if (! Storage::disk('local')->exists($path)) {
                    throw new RuntimeException("Newsletter attachment {$path} is missing; not sending this chunk.");
                }
            }

            return new NewsletterCustomMail(
                emailSubject: $this->newsletter['subject'],
                body: $this->newsletter['body'],
                attachmentPaths: $paths,
            );
        }

        $product = Product::with(['images', 'brand:id,name', 'category:id,name'])->find($this->newsletter['product_id']);

        if ($product === null) {
            throw new RuntimeException("Newsletter product #{$this->newsletter['product_id']} no longer exists; not sending this chunk.");
        }

        return new NewsletterProductMail(
            emailSubject: $this->newsletter['subject'],
            product: $product,
            selectedImage: $this->newsletter['selected_image'],
        );
    }
}
