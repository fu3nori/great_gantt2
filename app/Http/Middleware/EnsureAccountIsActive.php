<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $user->is_system_admin) {
            return $next($request);
        }
        abort_unless($user->isActive(), 403, 'このアカウントは利用停止中です。');
        $hasActiveOrganization = $user->organizationMemberships()->where('status', 'active')->whereHas('organization', fn ($q) => $q->where('status', 'active'))->exists();
        abort_unless($hasActiveOrganization, 403, '所属事業者が利用停止中です。');

        return $next($request);
    }
}
