<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerifyBrowserPassword
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession() && Auth::guard('web')->check()) {
            $storedHash = $request->session()->get('hustle_password_hash');
            if (! is_string($storedHash) || ! hash_equals($request->user()->getAuthPassword(), $storedHash)) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                throw new AuthenticationException('Unauthenticated.');
            }
        }

        return $next($request);
    }
}
