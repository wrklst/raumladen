<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Mail\StaffInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Backend users. There is no registration: an administrator invites a
 * person by email and gives them a role; the invitation link lets them set
 * their password (Auth\InvitationController).
 */
class StaffController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/staff', [
            'staff' => User::query()->where('role', '!=', UserRole::Customer)->orderBy('name')->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'pending' => $user->password === null,
                    'is_self' => $user->is($request->user()),
                ]),
            'roles' => array_map(fn (UserRole $role) => [
                'value' => $role->value,
                'label' => $role->label(),
                'description' => $role->description(),
            ], UserRole::staff()),
        ]);
    }

    /**
     * Invite a person.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::enum(UserRole::class)->only(UserRole::staff())],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'role' => $data['role'],
            'locale' => app()->getLocale(),
        ]);

        Mail::to($user->email)->send(new StaffInvitation($user, $request->user()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent to :email.', ['email' => $user->email])]);

        return back();
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isStaff(), 404);

        $data = $request->validate([
            'role' => ['required', Rule::enum(UserRole::class)->only(UserRole::staff())],
        ]);

        // Someone has to stay who can manage settings and users.
        if ($user->role === UserRole::Admin && $data['role'] !== UserRole::Admin->value && $this->isLastAdmin($user)) {
            return back()->withErrors(['role' => __('The last administrator cannot be given another role.')]);
        }

        $user->update(['role' => $data['role']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role changed.')]);

        return back();
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isStaff(), 404);

        if ($user->is($request->user())) {
            return back()->withErrors(['staff' => __('You cannot remove yourself.')]);
        }

        if ($user->role === UserRole::Admin && $this->isLastAdmin($user)) {
            return back()->withErrors(['staff' => __('The last administrator cannot be removed.')]);
        }

        // Someone who also ordered as a customer keeps their orders: they lose the backend, not the account.
        $user->orders()->exists()
            ? $user->update(['role' => UserRole::Customer, 'password' => null])
            : $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Backend access removed.')]);

        return back();
    }

    /**
     * Send the invitation again (the link in the first one expires).
     */
    public function invite(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isStaff() && $user->password === null, 404);

        Mail::to($user->email)->send(new StaffInvitation($user, $request->user()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent to :email.', ['email' => $user->email])]);

        return back();
    }

    private function isLastAdmin(User $user): bool
    {
        return ! User::query()->where('role', UserRole::Admin)->whereKeyNot($user->id)->whereNotNull('password')->exists();
    }
}
