<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\NewsletterUnsubscribeLinkMail;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class NewsletterController extends Controller
{
    public function subscribe(Request $request): RedirectResponse
    {
        // RFC 5321: an address is at most 254 characters, 64 of them before the @.
        $request->validate([
            'email' => ['required', 'email', 'max:254', 'regex:/^[^@]{1,64}@/'],
        ], [
            'email.max' => 'That email address is too long.',
            'email.regex' => 'That email address is too long.',
        ]);

        $subscriber = NewsletterSubscriber::where('email', $request->email)->first();

        if ($subscriber) {
            if ($subscriber->is_active) {
                return back()->with('newsletter_status', 'already_subscribed');
            }

            // FSA-14: a fresh token on reactivation invalidates every
            // unsubscribe link issued before this point — if an old one
            // ever leaked, it stops working the moment this subscriber
            // resubscribes, rather than remaining valid indefinitely.
            $subscriber->update([
                'is_active' => true,
                'subscribed_at' => now(),
                'unsubscribed_at' => null,
                'token' => Str::random(64),
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

    /**
     * FSA-14: shows a confirmation page for an unsubscribe link from an
     * email — never changes subscription state itself, so an email client
     * or security scanner that auto-opens links can't unsubscribe anyone.
     * A missing/wrong/unmatched (email, token) pair and a nonexistent email
     * render the same generic "invalid link" state, so this never confirms
     * whether an arbitrary email is even in the newsletter list.
     */
    public function unsubscribeConfirm(Request $request): Response
    {
        $email = (string) $request->query('email', '');
        $token = (string) $request->query('token', '');

        $subscriber = $email !== '' && $token !== ''
            ? NewsletterSubscriber::where('email', $email)->where('token', $token)->first()
            : null;

        if ($subscriber === null) {
            return Inertia::render('newsletter/Unsubscribe', ['status' => 'invalid']);
        }

        if (! $subscriber->is_active) {
            return Inertia::render('newsletter/Unsubscribe', [
                'status' => 'already_unsubscribed',
                'email' => $subscriber->email,
            ]);
        }

        return Inertia::render('newsletter/Unsubscribe', [
            'status' => 'confirm',
            'email' => $email,
            'token' => $token,
        ]);
    }

    /**
     * FSA-14: the email and token must both match the same subscriber row.
     * A missing, wrong or mismatched token updates nothing — the query
     * simply matches no rows, so there's nothing to leak or error on.
     */
    public function unsubscribe(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'token' => ['required', 'string', 'max:255'],
        ]);

        NewsletterSubscriber::where('email', $data['email'])
            ->where('token', $data['token'])
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'unsubscribed_at' => now(),
            ]);

        return redirect()->route('newsletter.unsubscribe.confirm', [
            'email' => $data['email'],
            'token' => $data['token'],
        ]);
    }

    /**
     * FSA-14: the public page linked from the generic "Manage subscription /
     * Unsubscribe" link in every newsletter campaign email (custom.blade.php,
     * product-promotion.blade.php). That link can't be personalized —
     * FSA-13 sends each chunk as one shared BCC message with one identical
     * body for up to 50 recipients — so this collects the visitor's email
     * and, separately, queues a one-to-one email containing their own
     * token link.
     */
    public function manage(): Response
    {
        return Inertia::render('newsletter/Manage');
    }

    /**
     * FSA-14: always returns the same generic response, whether or not the
     * email is an active subscriber, and whether or not queuing the
     * personalized email actually succeeds — none of those three outcomes
     * is distinguishable from the response alone. The personalized link is
     * only ever emailed to the subscriber's own stored address, never
     * returned in the response.
     */
    public function manageSubmit(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        $subscriber = NewsletterSubscriber::where('email', $data['email'])
            ->where('is_active', true)
            ->first();

        if ($subscriber !== null) {
            try {
                Mail::to($subscriber->email)
                    ->queue((new NewsletterUnsubscribeLinkMail($subscriber->email, $subscriber->token))->afterCommit());
            } catch (Throwable $e) {
                // Deliberately scoped to only this dispatch call, not the
                // whole method: a queue/mail outage must still fall through
                // to the exact same generic response below, never a 500 —
                // an uncaught exception here would only ever happen on the
                // "subscriber found" branch, which would itself disclose
                // that the submitted email is an active subscriber. The
                // email, subscriber email and token are never logged.
                Log::error('[Newsletter] Failed to queue an unsubscribe-management email.', [
                    'exception' => $e::class,
                ]);
            }
        }

        return back()->with('newsletter_status', 'manage_requested');
    }
}
