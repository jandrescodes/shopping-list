<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces HTTPS on every future visit once a client has been served once.
 * APP_URL is HTTPS-only, but without this header a client that lands on a
 * bare http:// link stays open to a downgrade until it happens to follow a
 * redirect.
 */
class Hsts
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        return $response;
    }
}
