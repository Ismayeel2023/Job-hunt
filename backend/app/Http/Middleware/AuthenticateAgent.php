<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAgent
{
    /**
     * Only accept loopback requests carrying the static AGENT_TOKEN.
     *
     * REMOTE_ADDR is read directly so a forwarded-for header can never satisfy the check.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->server('REMOTE_ADDR') !== '127.0.0.1') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $token = (string) config('services.agent.token');

        if ($token === '') {
            return response()->json(['message' => 'AGENT_TOKEN is not configured.'], 503);
        }

        if (! hash_equals($token, (string) $request->bearerToken())) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
