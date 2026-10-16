<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The list slug is the only access key and it lives in the URL path, so no
 * navigation or subresource request may carry it as a Referer to another
 * origin if an external link or asset is ever added to a view.
 */
class ReferrerPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
