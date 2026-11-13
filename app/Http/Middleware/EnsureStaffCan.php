<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards backend routes by area: `staff` lets every backend user in,
 * `staff:orders` only roles working in that area (App\Enums\UserRole).
 */
class EnsureStaffCan
{
    public function handle(Request $request, Closure $next, ?string $area = null): Response
    {
        $role = $request->user()?->backendRole();

        abort_unless($role?->isStaff() && ($area === null || $role->can($area)), 403);

        return $next($request);
    }
}
