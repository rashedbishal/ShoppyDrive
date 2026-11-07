<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\ContactFormMail;
use App\Models\Admin;
use App\Models\ContactMessage;
use App\Models\Setting;
use App\Support\LayoutCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ContactController extends Controller
{
    /**
     * Defaults mirror the page's original hardcoded copy, so the page renders
     * identically until an admin edits it from Admin > Content > Contact Page.
     */
    public function show(): Response
    {
        $settings = Setting::whereIn('key', $this->keys())->pluck('value', 'key');

        return Inertia::render('static/Contact', [
            'content' => [
                'hero_eyebrow' => $settings['contact_page_hero_eyebrow'] ?? 'Get in touch',
                'hero_title' => $settings['contact_page_hero_title'] ?? "We're here to",
                'hero_title_highlight' => $settings['contact_page_hero_title_highlight'] ?? 'help',
                'hero_description' => $settings['contact_page_hero_description'] ?? 'Have a question, complaint, or just want to say hello? Our team is ready to assist you.',
                'phone' => $settings['contact_phone'] ?? '+880 1700-000000',
                'email' => $settings['contact_email'] ?? 'support@shoppydrive.com',
                'address' => $settings['contact_address'] ?? 'Dhaka, Bangladesh',
                'support_hours' => $settings['contact_page_support_hours'] ?? 'Every day, 9 AM–9 PM',
                'social_links' => $settings['social_links'] ?? [
                    'facebook' => 'https://facebook.com/shoppydrive',
                    'instagram' => 'https://instagram.com/shoppydrive',
                    'twitter' => 'https://twitter.com/shoppydrive',
                ],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $this->notifyShop(ContactMessage::create($data));

        return back()->with('success', 'Message sent.');
    }

    /**
     * The message is already saved, so a mail problem must not make the
     * customer think it failed.
     */
    private function notifyShop(ContactMessage $contactMessage): void
    {
        $recipient = LayoutCache::settings()['contact_email'] ?? null;
        $recipient = filled($recipient) ? $recipient : Admin::where('is_mother', true)->value('email');

        if (blank($recipient)) {
            return;
        }

        try {
            Mail::to($recipient)->queue(new ContactFormMail($contactMessage));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /** @return array<int, string> */
    private function keys(): array
    {
        return [
            'contact_page_hero_eyebrow', 'contact_page_hero_title', 'contact_page_hero_title_highlight',
            'contact_page_hero_description', 'contact_page_support_hours',
            'contact_phone', 'contact_email', 'contact_address', 'social_links',
        ];
    }
}
