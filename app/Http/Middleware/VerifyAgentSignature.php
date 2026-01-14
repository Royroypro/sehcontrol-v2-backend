<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyAgentSignature
{
    public function handle(Request $request, Closure $next)
    {
        /*
        |--------------------------------------------------------------------------
        | 1) Si el request viene con Bearer token, NO exigir firma HMAC
        |--------------------------------------------------------------------------
        | El token ya autentica al dispositivo (modelo moderno y recomendado).
        */
        if ($request->bearerToken()) {
            return $next($request);
        }

        /*
        |--------------------------------------------------------------------------
        | 2) Legacy / fallback: exigir HMAC si NO hay Bearer token
        |--------------------------------------------------------------------------
        */
        $secret = config('sehcontrol.agent_secret');

        if (!$secret) {
            return response()->json([
                'message' => 'Agent auth not configured',
                'reason'  => 'missing_agent_secret',
            ], 500);
        }

        $provided = $request->header('X-SEH-SIGN');

        if (!$provided) {
            return response()->json([
                'message' => 'Unauthorized',
                'reason'  => 'missing_signature',
            ], 401);
        }

        // Importante: el body RAW (tal como lo hace el agente)
        $raw = $request->getContent();

        $calculated = hash_hmac('sha256', $raw, $secret);

        if (!hash_equals($calculated, $provided)) {
            return response()->json([
                'message' => 'Unauthorized',
                'reason'  => 'bad_signature',
            ], 401);
        }

        return $next($request);
    }
}

