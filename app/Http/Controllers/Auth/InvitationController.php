<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Accepting an invitation to the backend: the signed link from the
 * invitation mail leads here, the invited person sets a password and is
 * signed in. The link works once (until a password exists) and expires.
 */
class InvitationController extends Controller
{
    public function show(Request $request, User $user): Response
    {
        $this->ensurePending($user);

        return Inertia::render('auth/accept-invitation', [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->label(),
            // The signature is part of the address; the form posts back to it.
            'action' => $request->fullUrl(),
        ]);
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        $this->ensurePending($user);

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->forceFill([
            'password' => $data['password'],
            'email_verified_at' => now(),
        ])->save();

        Auth::login($user);
        $request->session()->regenerate();

        return to_route('admin.dashboard');
    }

    private function ensurePending(User $user): void
    {
        abort_unless($user->isStaff() && $user->password === null, 410);
    }
}
