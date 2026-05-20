<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NewsletterController extends Controller
{
    public function subscribe(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:255']]);

        $subscriber = NewsletterSubscriber::where('email', $request->email)->first();

        if ($subscriber) {
            if ($subscriber->is_active) {
                return back()->with('newsletter_status', 'already_subscribed');
            }

            $subscriber->update([
                'is_active' => true,
                'subscribed_at' => now(),
                'unsubscribed_at' => null,
            ]);
        } else {
            NewsletterSubscriber::create([
                'email' => $request->email,
                'token' => Str::random(64),
                'is_active' => true,
                'subscribed_at' => now(),
            ]);
        }

        return back()->with('newsletter_status', 'subscribed');
    }

    public function unsubscribe(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:255']]);

        NewsletterSubscriber::where('email', $request->email)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'unsubscribed_at' => now(),
            ]);

        return back()->with('newsletter_status', 'unsubscribed');
    }

    public function status(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:255']]);

        $isSubscribed = NewsletterSubscriber::where('email', $request->email)
            ->where('is_active', true)
            ->exists();

        return response()->json(['is_subscribed' => $isSubscribed]);
    }
}
