<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restreint une route à un module de l'espace producteur (voir User::MODULES).
 *
 * Usage : ->middleware('module:talents'). L'admin et le producteur titulaire
 * passent toujours ; un membre d'équipe seulement si le module lui a été
 * délégué.
 */
class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('admin.login');
        }

        if (! $user->canAccessModule($module)) {
            abort(403, "Ce module ne vous a pas été attribué.");
        }

        return $next($request);
    }
}
