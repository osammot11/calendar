<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMcpBridgeToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.mcp.bridge_token');
        $provided = $request->bearerToken();

        if (! is_string($expected) || strlen($expected) < 32 || ! is_string($provided) || ! hash_equals($expected, $provided)) {
            abort(403);
        }

        return $next($request);
    }
}
