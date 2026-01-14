<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyAdminKey
{
    public function handle(Request $request, Closure $next)
    {
        $key = config('sehcontrol.admin_key');

        if (!$key) {
            return response()->json([
                'message' => 'Admin key not configured',
                'reason'  => 'missing_admin_key',
            ], 500);
        }

        $provided = $request->header('X-SEH-ADMIN');

        if (!$provided || !hash_equals($key, $provided)) {
            return response()->json([
                'message' => 'Unauthorized',
                'reason'  => 'bad_admin_key',
            ], 401);
        }

        return $next($request);
    }
}
