<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMcpScope
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user('api')?->tokenCan('mcp:use'), 401);

        return $next($request);
    }
}
