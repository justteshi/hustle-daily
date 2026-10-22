<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = (string) Str::uuid();
        $request->attributes->set('request_id', $id);
        if ($request->is('api/*')) {
            App::setLocale($request->getPreferredLanguage(['bg', 'en']) ?? 'en');
        }
        $response = $next($request);
        $response->headers->set('X-Request-ID', $id);

        return $response;
    }
}
