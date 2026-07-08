<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // Log pour diagnostic
        Log::info('CheckRole', [
            'user_id'       => $user?->id,
            'user_role'     => $user?->role,
            'required_roles'=> $roles,
            'token_present' => $request->bearerToken() ? 'oui' : 'non',
            'url'           => $request->url(),
        ]);

        if (!$user) {
            return response()->json([
                'message'       => 'Non authentifié.',
                'token_present' => $request->bearerToken() ? 'oui' : 'non',
            ], 401);
        }

        if (!in_array($user->role, $roles)) {
            return response()->json([
                'message'       => 'Accès refusé.',
                'your_role'     => $user->role,
                'required_roles'=> $roles,
                'user_id'       => $user->id,
            ], 403);
        }

        return $next($request);
    }
}