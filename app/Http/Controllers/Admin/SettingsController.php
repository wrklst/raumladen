<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Settings\GoLiveCheck;
use App\Settings\ShopSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Shop settings in the backend: seller, payment, mail, shipping, tax, hosting.
 */
class SettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/settings', [
            'groups' => collect(ShopSettings::groups())->map(fn (array $group, string $key) => [
                'key' => $key,
                'label' => $group['label'],
                'description' => $group['description'],
                'fields' => array_map(fn (array $field) => [
                    'name' => $field['name'],
                    'type' => $field['type'],
                    'label' => $field['label'],
                    'help' => $field['help'] ?? null,
                    'options' => $field['options'] ?? null,
                    'required' => in_array('required', $field['rules'], true),
                ], $group['fields']),
                'values' => ShopSettings::values($key),
            ])->values(),
            'problems' => GoLiveCheck::problems(),
        ]);
    }

    public function update(Request $request, string $group): RedirectResponse
    {
        $definition = ShopSettings::groups()[$group] ?? abort(404);

        $data = $request->validate(
            collect($definition['fields'])->mapWithKeys(fn (array $field) => [$field['name'] => $field['rules']])->all(),
            attributes: collect($definition['fields'])->mapWithKeys(fn (array $field) => [$field['name'] => $field['label']])->all(),
        );

        ShopSettings::save($group, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':group saved.', ['group' => $definition['label']])]);

        return back();
    }

    /**
     * Send a test mail with the saved mail settings to the signed-in admin.
     */
    public function testMail(Request $request): RedirectResponse
    {
        $address = $request->user()->email;

        try {
            Mail::raw(__('This is a test message from :shop. Email delivery works.', ['shop' => config('app.name')]), function ($message) use ($address): void {
                $message->to($address)->subject(__('Test message from :shop', ['shop' => config('app.name')]));
            });
        } catch (Throwable $e) {
            return back()->withErrors(['mail_test' => __('The test message could not be sent: :reason', ['reason' => $e->getMessage()])]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test message sent to :email.', ['email' => $address])]);

        return back();
    }
}
