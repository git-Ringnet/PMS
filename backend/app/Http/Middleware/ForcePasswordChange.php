<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    private function isAllowedRoute(Request $request): bool
    {
        return $request->is(
            'api/login',
            'api/logout',
            'api/me',
            'api/me/change-password',
            'api/hotel-settings',
        );
    }
}
