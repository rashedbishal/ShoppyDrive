<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Concerns\RecordsAdminActions;
use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ContactMessageController extends Controller
{
    use RecordsAdminActions;

    public function index(Request $request): Response
    {
        $messages = ContactMessage::query()
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%")
                ->orWhere('subject', 'like', "%{$request->search}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/contact-messages/index', [
            'messages' => $messages,
            'filters' => $request->only(['search']),
        ]);
    }

    public function destroy(ContactMessage $contactMessage): RedirectResponse
    {
        DB::transaction(function () use ($contactMessage): void {
            $contactMessage->delete();

            $this->recordAdminAction('deleted', "Contact message #{$contactMessage->id} deleted", $contactMessage, [
                'email' => $this->maskedEmail($contactMessage->email),
            ]);
        });

        return back()->with('success', 'Message deleted.');
    }

    public function clear(): RedirectResponse
    {
        DB::transaction(function (): void {
            $count = ContactMessage::query()->delete();

            if ($count > 0) {
                $this->recordAdminAction('cleared', "All contact messages cleared ({$count})", properties: ['count' => $count]);
            }
        });

        return back()->with('success', 'All messages cleared.');
    }
}
