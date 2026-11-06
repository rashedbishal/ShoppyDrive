<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Address;
use App\Support\BangladeshDistricts;
use App\Support\BangladeshiPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AddressController extends Controller
{
    public function index(Request $request): Response
    {
        $addresses = Address::where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->get();

        return Inertia::render('account/Addresses', [
            'addresses' => $addresses,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateAddress($request);

        if ($validated['is_default']) {
            Address::where('user_id', $request->user()->id)->update(['is_default' => false]);
        }

        $request->user()->addresses()->create($validated);

        return back()->with('success', 'Address saved.');
    }

    public function update(Request $request, Address $address): RedirectResponse
    {
        abort_if($address->user_id !== $request->user()->id, 403);

        $validated = $this->validateAddress($request);

        if ($validated['is_default']) {
            Address::where('user_id', $request->user()->id)
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);
        }

        $address->update($validated);

        return back()->with('success', 'Address updated.');
    }

    public function destroy(Request $request, Address $address): RedirectResponse
    {
        abort_if($address->user_id !== $request->user()->id, 403);

        $address->delete();

        return back()->with('success', 'Address deleted.');
    }

    public function setDefault(Request $request, Address $address): RedirectResponse
    {
        abort_if($address->user_id !== $request->user()->id, 403);

        Address::where('user_id', $request->user()->id)->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return back()->with('success', 'Default address updated.');
    }

    private function validateAddress(Request $request): array
    {
        // Normalize before validating: an unchecked checkbox simply omits
        // the key, and `boolean` alone (without `sometimes`/a default) then
        // leaves it out of validated() entirely, so $validated['is_default']
        // below would throw on a key that was never sent.
        $request->merge(['is_default' => $request->boolean('is_default')]);

        // FE-012: same cleaning and rule as checkout, so a saved address
        // can never fail checkout later because of its phone format.
        if (is_string($request->input('phone'))) {
            $request->merge(['phone' => BangladeshiPhone::normalize($request->input('phone'))]);
        }

        return $request->validate([
            'label' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:'.BangladeshiPhone::PATTERN],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:255', Rule::in(BangladeshDistricts::ALL)],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:100'],
            'is_default' => ['boolean'],
        ], [
            'phone.regex' => BangladeshiPhone::INVALID_MESSAGE,
        ]);
    }
}
