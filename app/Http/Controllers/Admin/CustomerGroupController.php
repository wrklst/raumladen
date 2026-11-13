<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CustomerGroupController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/customer-groups/index', [
            'groups' => CustomerGroup::query()->withCount('users')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data): void {
            $this->clearDefaultIf($data['is_default']);
            CustomerGroup::query()->create($data);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer group created.')]);

        return back();
    }

    public function update(Request $request, CustomerGroup $customerGroup): RedirectResponse
    {
        $data = $this->validated($request, $customerGroup);

        DB::transaction(function () use ($data, $customerGroup): void {
            $this->clearDefaultIf($data['is_default']);
            // forceFill + save on a fresh copy: the loaded model may already say
            // is_default = true and would then write nothing after the clear.
            $customerGroup->refresh()->update($data);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer group updated.')]);

        return back();
    }

    public function destroy(CustomerGroup $customerGroup): RedirectResponse
    {
        abort_if($customerGroup->is_default || $customerGroup->users()->exists(), 422, __('Group is in use.'));

        $customerGroup->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer group deleted.')]);

        return back();
    }

    /**
     * @return array{code: string, name: string, prices_include_tax: bool, is_default: bool}
     */
    private function validated(Request $request, ?CustomerGroup $group = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'alpha_dash:ascii', 'max:30', Rule::unique(CustomerGroup::class)->ignore($group?->id)],
            'name' => ['required', 'string', 'max:100'],
            'prices_include_tax' => ['boolean'],
            'is_default' => ['boolean'],
        ]);

        return [
            'code' => mb_strtolower($data['code']),
            'name' => $data['name'],
            'prices_include_tax' => $request->boolean('prices_include_tax'),
            'is_default' => $request->boolean('is_default') || ($group->is_default ?? false),
        ];
    }

    private function clearDefaultIf(bool $isDefault): void
    {
        if ($isDefault) {
            CustomerGroup::query()->where('is_default', true)->update(['is_default' => false]);
        }
    }
}
