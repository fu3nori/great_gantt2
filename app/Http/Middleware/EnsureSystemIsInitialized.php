<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSystemIsInitialized
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! User::query()->where('is_system_admin', true)->exists()) {
            return redirect()->route('setup.admin.create');
        }

        return $next($request);
    }
}
