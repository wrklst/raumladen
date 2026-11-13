<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Seo\Seo;
use App\Support\Countries;
use App\Tax\VatIdValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('account/profile', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'company' => $user->company,
                'vat_id' => $user->vat_id,
                'vat_id_valid' => $user->hasValidVatId(),
                'phone' => $user->phone,
                'locale' => $user->locale,
                'billing_address' => $user->billing_address,
                'shipping_address' => $user->shipping_address,
                'customer_group' => $user->customerGroup?->name,
            ],
            'countries' => Countries::options(),
            'seo' => Seo::make(__('Your profile'), null, route('shop.account.profile.edit'), [], index: false),
        ]);
    }

    public function update(Request $request, VatIdValidator $validator): RedirectResponse
    {
        $address = fn (string $prefix) => [
            "{$prefix}.first_name" => ['nullable', 'string', 'max:100'],
            "{$prefix}.last_name" => ['nullable', 'string', 'max:100'],
            "{$prefix}.company" => ['nullable', 'string', 'max:150'],
            "{$prefix}.address1" => ['nullable', 'string', 'max:150'],
            "{$prefix}.address2" => ['nullable', 'string', 'max:150'],
            "{$prefix}.zip" => ['nullable', 'string', 'max:20'],
            "{$prefix}.city" => ['nullable', 'string', 'max:100'],
            "{$prefix}.country" => ['nullable', 'string', 'size:2'],
        ];

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:150'],
            'vat_id' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:50'],
            'locale' => ['nullable', Rule::in(config('shop.locales'))],
            ...$address('billing_address'),
            ...$address('shipping_address'),
        ]);

        $vatValidatedAt = null;

        if (! empty($data['vat_id'])) {
            $result = $validator->validate($data['vat_id']);
            $data['vat_id'] = $result->vatId;

            if (! $result->valid && $result->serviceAvailable) {
                return back()->withErrors(['vat_id' => __('This VAT ID could not be confirmed.')]);
            }

            $vatValidatedAt = $result->valid ? now() : null;
        }

        $request->user()->update([...$data, 'vat_id_validated_at' => $vatValidatedAt]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return back();
    }
}
