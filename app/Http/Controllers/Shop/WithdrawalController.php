<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Mail\WithdrawalReceived;
use App\Models\Order;
use App\Models\Withdrawal;
use App\Seo\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Electronic withdrawal function (§ 356a BGB): a statement form reachable
 * from every page, confirmed with one button, acknowledged by email with
 * its content and the time of receipt.
 */
class WithdrawalController extends Controller
{
    public function create(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('shop/withdrawal', [
            'defaults' => [
                'order_number' => $request->string('order')->limit(50, '')->toString(),
                'name' => $user?->name,
                'email' => $user?->email,
            ],
            'received_at' => $request->session()->get('withdrawal_received_at'),
            'seo' => Seo::make(__('Withdraw from contract'), null, route('shop.withdrawal.create'), Seo::alternates('shop.withdrawal.create'), index: false),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'order_number' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'statement' => ['nullable', 'string', 'max:2000'],
        ]);

        // Linked only when number and email belong together; the declaration
        // is valid and stored either way, and the response never tells.
        $order = Order::query()
            ->where('number', trim($data['order_number']))
            ->where('email', mb_strtolower($data['email']))
            ->first();

        $withdrawal = Withdrawal::query()->create([
            ...$data,
            'order_id' => $order?->id,
            'locale' => app()->getLocale(),
        ]);

        Mail::to($withdrawal->email)->locale($withdrawal->locale)->send(new WithdrawalReceived($withdrawal));
        Mail::to(config('shop.seller.email'))->locale(config('shop.locales')[0])->send(new WithdrawalReceived($withdrawal));

        return to_route('shop.withdrawal.create')
            ->with('withdrawal_received_at', $withdrawal->created_at->toIso8601String());
    }
}
