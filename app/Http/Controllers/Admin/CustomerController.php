<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use App\Models\User;
use App\Tax\VatIdValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('q')->toString();

        $customers = User::query()
            ->where('role', UserRole::Customer)
            ->with('customerGroup')
            ->withCount('orders')
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('company', 'like', "%{$search}%")))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'company' => $user->company,
                'group' => $user->customerGroup?->name,
                'vat_id' => $user->vat_id,
                'vat_id_valid' => $user->hasValidVatId(),
                'orders_count' => $user->orders_count,
                'created_at' => $user->created_at?->toDateString(),
            ]);

        return Inertia::render('admin/customers/index', [
            'customers' => $customers,
            'filters' => ['q' => $search],
        ]);
    }

    public function edit(User $user): Response
    {
        abort_unless($user->role === UserRole::Customer, 404);

        return Inertia::render('admin/customers/edit', [
            'customer' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'company' => $user->company,
                'vat_id' => $user->vat_id,
                'vat_id_valid' => $user->hasValidVatId(),
                'customer_group_id' => $user->customer_group_id,
                'billing_address' => $user->billing_address,
                'shipping_address' => $user->shipping_address,
            ],
            'groups' => CustomerGroup::query()->orderBy('id')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, User $user, VatIdValidator $validator): RedirectResponse
    {
        abort_unless($user->role === UserRole::Customer, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'company' => ['nullable', 'string', 'max:150'],
            'vat_id' => ['nullable', 'string', 'max:20'],
            'vat_id_valid' => ['boolean'],
            'customer_group_id' => ['required', Rule::exists(CustomerGroup::class, 'id')],
        ]);

        $vatId = empty($data['vat_id']) ? null : VatIdValidator::normalize($data['vat_id']);
        $validatedAt = null;

        if ($vatId !== null) {
            $validatedAt = $request->boolean('vat_id_valid') || $validator->validate($vatId)->valid ? now() : null;
        }

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'company' => $data['company'] ?? null,
            'vat_id' => $vatId,
            'vat_id_validated_at' => $validatedAt,
            'customer_group_id' => $data['customer_group_id'],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer updated.')]);

        return back();
    }
}
