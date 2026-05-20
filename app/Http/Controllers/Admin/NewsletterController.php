<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\NewsletterCustomMail;
use App\Mail\NewsletterProductMail;
use App\Models\NewsletterSubscriber;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class NewsletterController extends Controller
{
    public function index(Request $request): Response
    {
        $subscribers = NewsletterSubscriber::query()
            ->when($request->search, fn ($q) => $q->where('email', 'like', "%{$request->search}%"))
            ->when($request->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/newsletter/index', [
            'subscribers' => $subscribers,
            'filters' => $request->only(['search', 'status']),
            'total_active' => NewsletterSubscriber::where('is_active', true)->count(),
        ]);
    }

    public function destroy(NewsletterSubscriber $subscriber): RedirectResponse
    {
        $subscriber->delete();

        return back()->with('success', 'Subscriber removed.');
    }

    public function compose(): Response
    {
        $products = Product::with(['media' => fn ($q) => $q->orderBy('is_primary', 'desc')->orderBy('sort_order')])
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

        $activeEmails = NewsletterSubscriber::where('is_active', true)->pluck('email')->toArray();

        if (empty($activeEmails)) {
            return back()->with('error', 'No active subscribers to send to.');
        }

        if ($type === 'custom') {
            $data = $request->validate([
                'subject' => ['required', 'string', 'max:255'],
                'body' => ['required', 'string'],
                'attachments' => ['nullable', 'array'],
                'attachments.*' => ['file', 'max:10240'],
            ]);

            $attachmentPaths = [];
            foreach ($request->file('attachments', []) as $file) {
                $attachmentPaths[] = $file->store('newsletter-attachments/tmp', 'local');
            }

            Mail::to(config('mail.from.address'))
                ->bcc($activeEmails)
                ->send(new NewsletterCustomMail(
                    emailSubject: $data['subject'],
                    body: $data['body'],
                    attachmentPaths: $attachmentPaths,
                ));
        } else {
            $data = $request->validate([
                'subject' => ['required', 'string', 'max:255'],
                'product_id' => ['required', 'exists:products,id'],
                'selected_image' => ['nullable', 'string'],
            ]);

            $product = Product::with(['media', 'brand:id,name', 'category:id,name'])->findOrFail($data['product_id']);

            Mail::to(config('mail.from.address'))
                ->bcc($activeEmails)
                ->send(new NewsletterProductMail(
                    emailSubject: $data['subject'],
                    product: $product,
                    selectedImage: $data['selected_image'] ?? null,
                ));
        }

        return redirect()->route('admin.newsletter.index')
            ->with('success', 'Newsletter sent to '.count($activeEmails).' subscribers.');
    }
}
