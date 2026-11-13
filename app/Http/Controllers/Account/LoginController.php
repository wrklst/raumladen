<?php

namespace App\Http\Controllers\Account;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Mail\LoginCode;
use App\Models\CustomerGroup;
use App\Models\User;
use App\Seo\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Passwordless customer login: a one-time code sent by email.
 */
class LoginController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->user() !== null) {
            return to_route('shop.account.orders.index');
        }

        return Inertia::render('account/login', [
            'email' => $request->session()->get('login.email'),
            'seo' => Seo::make(__('Sign in'), null, route('shop.account.login'), Seo::alternates('shop.account.login'), index: false),
        ]);
    }

    public function sendCode(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $email = mb_strtolower($data['email']);

        if (User::query()->where('email', $email)->where('role', UserRole::Admin)->exists()) {
            return back()->withErrors(['email' => __('Administrators sign in with their password.')]);
        }

        $code = (string) random_int(10 ** (config('shop.login_code.length') - 1), 10 ** config('shop.login_code.length') - 1);

        Cache::put($this->cacheKey($email), Hash::make($code), now()->addMinutes(config('shop.login_code.ttl_minutes')));
        $request->session()->put('login.email', $email);

        Mail::to($email)->locale(app()->getLocale())->send(new LoginCode($code));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('We sent a login code to :email.', ['email' => $email])]);

        return back();
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:'.config('shop.login_code.length')]]);
        $email = $request->session()->get('login.email');

        $hash = $email === null ? null : Cache::get($this->cacheKey($email));

        if ($hash === null || ! Hash::check($data['code'], $hash)) {
            return back()->withErrors(['code' => __('This code is invalid or has expired.')]);
        }

        Cache::forget($this->cacheKey($email));
        $request->session()->forget('login.email');

        $user = User::query()->firstOrCreate(['email' => $email], [
            'name' => Str::before($email, '@'),
            'role' => UserRole::Customer,
            'customer_group_id' => CustomerGroup::default()->id,
            'locale' => app()->getLocale(),
        ]);

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('shop.account.orders.index'));
    }

    private function cacheKey(string $email): string
    {
        return 'login_code:'.sha1($email);
    }
}
