<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vérifie que l'utilisateur authentifié (via Sanctum) a le rôle 'admin'.
 * À utiliser sur les routes API d'administration : `->middleware(['auth:sanctum','admin'])`.
 */
class EnsureIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! method_exists($user, 'isAdmin') || ! $user->isAdmin()) {
            return response()->json([
                'message' => 'Accès interdit : rôle administrateur requis.',
            ], 403);
        }

        return $next($request);
    }
}
