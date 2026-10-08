<?php

use App\Http\Middleware\EnsureMcpScope;
use App\Mcp\Servers\CalendarServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Route::get('/.well-known/oauth-authorization-server', fn () => response()->json([
    'issuer' => url('/'),
    'authorization_endpoint' => route('passport.authorizations.authorize'),
    'token_endpoint' => route('passport.token'),
    'registration_endpoint' => url('/oauth/register'),
    'response_types_supported' => ['code'],
    'grant_types_supported' => ['authorization_code', 'refresh_token'],
    'code_challenge_methods_supported' => ['S256'],
    'token_endpoint_auth_methods_supported' => ['none'],
    'scopes_supported' => ['mcp:use'],
]));

Route::middleware('throttle:10,1')->group(fn () => Mcp::oauthRoutes());

Mcp::web('/mcp/calendar', CalendarServer::class)->middleware(['auth:api', EnsureMcpScope::class]);
