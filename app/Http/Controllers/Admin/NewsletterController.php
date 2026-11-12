<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Concerns\RecordsAdminActions;
use App\Console\Commands\DrainQueueCommand;
use App\Http\Controllers\Controller;
use App\Jobs\SendNewsletterChunk;
use App\Models\NewsletterSubscriber;
use App\Models\Product;
use App\Services\ImageOptimizer;
use App\Support\AdminListUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Mews\Purifier\Facades\Purifier;

class NewsletterController extends Controller
{
    use RecordsAdminActions;

    /**
     * FSA-13: BCC recipients per message — well under common shared-hosting
     * SMTP per-message recipient caps (typically 100).
     */
    public const RECIPIENTS_PER_MESSAGE = 50;

    /** OPS-004: 50 recipients every 5 minutes is 600 emails an hour. */
    private const MINUTES_BETWEEN_CHUNKS = 5;

    /** The email is 600px wide; this keeps its product image sharp on 2x screens. */
    private const EMAIL_IMAGE_MAX_SIDE = 1200;

    /**
     * Each send's uploads live in their own `{ATTACHMENT_ROOT}/{sendId}`
     * directory on the local disk, removed when its batch completes.
     */
    public const ATTACHMENT_ROOT = 'newsletter-attachments';

    public const BATCH_NAME_PREFIX = 'newsletter:';

    public function index(Request $request): Response|RedirectResponse
    {
        $subscribers = NewsletterSubscriber::query()
            ->when($request->search, fn ($q) => $q->where('email', 'like', "%{$request->search}%"))
            ->when($request->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $pastLastPage = AdminListUrl::redirectIfPastLastPage($subscribers, $request);

        if ($pastLastPage !== null) {
            return $pastLastPage;
        }

        return Inertia::render('admin/newsletter/index', [
            'subscribers' => $subscribers,
            'filters' => $request->only(['search', 'status']),
            'total_active' => NewsletterSubscriber::where('is_active', true)->count(),
        ]);
    }

    public function destroy(NewsletterSubscriber $subscriber): RedirectResponse
    {
        DB::transaction(function () use ($subscriber): void {
            $subscriber->delete();

            $this->recordAdminAction('deleted', "Newsletter subscriber #{$subscriber->id} removed", $subscriber, [
                'email' => $this->maskedEmail($subscriber->email),
            ]);
        });

        return back()->with('success', 'Subscriber removed.');
    }

    /**
     * The address of an email-safe copy of a product image (JPEG, white
     * background, at most EMAIL_IMAGE_MAX_SIDE), made once per send so every
     * chunk shares it, and kept afterwards since people open emails later.
     * Falls back to the original image if the copy can't be made; an image
     * hosted elsewhere is used as it is.
     */
    private function emailImagePath(?string $path): ?string
    {
        if ($path === null || ! str_starts_with($path, '/storage/')) {
            return $path;
        }

        $bytes = app(ImageOptimizer::class)->emailJpeg(
            Storage::disk('public')->path(substr($path, strlen('/storage/'))),
            self::EMAIL_IMAGE_MAX_SIDE,
        );

        if ($bytes === null) {
            return $path;
        }

        $copy = 'newsletter-images/'.Str::random(40).'.jpg';

        return Storage::disk('public')->put($copy, $bytes) ? '/storage/'.$copy : $path;
    }

    public function compose(): Response
    {
        // Images only: the email shows one picture, and a video can't be one.
        $products = Product::with(['media' => fn ($q) => $q->where('type', 'image')->orderBy('is_primary', 'desc')->orderBy('sort_order')])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'price', 'compare_price', 'short_description']);

        return Inertia::render('admin/newsletter/compose', [
            'products' => $products,
            'active_subscribers_count' => NewsletterSubscriber::where('is_active', true)->count(),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $type = $request->validate(['type' => ['required', 'in:custom,product_promotion']])['type'];

        // FSA-13: one entry per subscriber (case-insensitively), sent in
        // queued chunks instead of one synchronous all-BCC message.
        $activeEmails = NewsletterSubscriber::where('is_active', true)
            ->orderBy('id')
            ->pluck('email')
            ->unique(fn (string $email): string => mb_strtolower(trim($email)))
            ->values();

        if ($activeEmails->isEmpty()) {
            return back()->with('error', 'No active subscribers to send to.');
        }

        $sendId = (string) Str::uuid();
        $attachmentDirectory = self::ATTACHMENT_ROOT.'/'.$sendId;

        if ($type === 'custom') {
            $data = $request->validate([
                'subject' => ['required', 'string', 'max:150'],
                'body' => ['required', 'string', 'max:20000'],
                'attachments' => ['nullable', 'array'],
                'attachments.*' => ['file', 'max:10240'],
            ]);

            $attachmentPaths = [];
            foreach ($request->file('attachments', []) as $file) {
                $attachmentPaths[] = $file->store($attachmentDirectory, 'local');
            }

            $newsletter = [
                'type' => 'custom',
                'subject' => $data['subject'],
                'body' => Purifier::clean($data['body']),
                'attachment_paths' => $attachmentPaths,
            ];
        } else {
            $data = $request->validate([
                'subject' => ['required', 'string', 'max:150'],
                'product_id' => ['required', 'exists:products,id'],
                'selected_image' => ['nullable', 'string', 'max:500'],
            ]);

            $product = Product::with('images')->findOrFail($data['product_id']);
            $imagePaths = $product->images->pluck('path')->all();

            // Only one of this product's own images: never a video, and
            // never an arbitrary address typed into the request.
            if (isset($data['selected_image']) && ! in_array($data['selected_image'], $imagePaths, true)) {
                throw ValidationException::withMessages([
                    'selected_image' => 'Choose one of this product\'s images.',
                ]);
            }

            $newsletter = [
                'type' => 'product_promotion',
                'subject' => $data['subject'],
                'product_id' => $product->id,
                'selected_image' => $this->emailImagePath($data['selected_image'] ?? $product->primaryImagePath()),
            ];
        }

        $jobs = $activeEmails
            ->chunk(self::RECIPIENTS_PER_MESSAGE)
            ->values()
            // OPS-004: a pause between chunks, so one big send can't use up
            // the mail account's limit, on a queue that order mail goes ahead of.
            ->map(fn ($chunk, int $index): SendNewsletterChunk => (new SendNewsletterChunk($chunk->values()->all(), $newsletter))
                ->delay(now()->addMinutes($index * self::MINUTES_BETWEEN_CHUNKS)))
            ->all();

        // `then` runs only once every chunk has succeeded — including a
        // failed chunk later retried successfully — so attachments are never
        // removed while any chunk may still need them. allowFailures() keeps
        // one bad chunk from cancelling the others.
        Bus::batch($jobs)
            ->onQueue(DrainQueueCommand::NEWSLETTER_QUEUE)
            ->name(self::BATCH_NAME_PREFIX.$sendId)
            ->allowFailures()
            ->then(static fn () => Storage::disk('local')->deleteDirectory($attachmentDirectory))
            ->dispatch();

        return redirect()->route('admin.newsletter.index')
            ->with('success', 'Newsletter queued for '.$activeEmails->count().' subscribers in '.count($jobs).' message(s). One goes out every '.self::MINUTES_BETWEEN_CHUNKS.' minutes, so this send takes about '.$this->sendDuration(count($jobs)).'.');
    }

    private function sendDuration(int $chunks): string
    {
        $minutes = max(0, $chunks - 1) * self::MINUTES_BETWEEN_CHUNKS;

        return $minutes < 60 ? "{$minutes} minutes" : round($minutes / 60, 1).' hours';
    }
}
