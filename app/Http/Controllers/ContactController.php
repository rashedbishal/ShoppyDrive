<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\ContactFormMail;
use App\Models\Admin;
use App\Models\ContactMessage;
use App\Models\Setting;
use App\Support\LayoutCache;
use App\Support\PageSeo;
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
        $settings = Setting::whereIn('key', self::keys())->pluck('value', 'key');

        $content = [
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
        ];

        // DISC-87: the admin's SEO title/description (Admin › Contact Page),
        // else the title and intro text (DISC-76). Contact.tsx shows the
        // same title in the browser tab.
        $seoTitle = self::filledText($settings['contact_page_meta_title'] ?? null) ?? 'Contact Us';
        PageSeo::share(
            $seoTitle,
            self::filledText($settings['contact_page_meta_description'] ?? null) ?? self::filledText($content['hero_description']),
        );

        return Inertia::render('static/Contact', ['content' => $content, 'seo_title' => $seoTitle]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:2000'],
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

    private static function filledText(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * The Setting rows this page reads; the sitemap dates the page by them.
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return [
            'contact_page_hero_eyebrow', 'contact_page_hero_title', 'contact_page_hero_title_highlight',
            'contact_page_hero_description', 'contact_page_support_hours',
            'contact_phone', 'contact_email', 'contact_address', 'social_links',
            'contact_page_meta_title', 'contact_page_meta_description',
        ];
    }
}
