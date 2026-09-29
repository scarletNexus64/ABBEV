<?php

namespace App\Http\Middleware;

use App\Support\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cloisonne le panel web à l'espace du producteur connecté (voir
 * App\Support\Workspace). Placé AVANT SubstituteBindings dans la liste de
 * priorité : un modèle d'un autre producteur passé dans l'URL n'est même pas
 * trouvé (404).
 *
 * Le contexte est levé en fin de requête pour ne jamais fuiter vers la
 * suivante (tests, workers longue durée).
 */
class ScopeToWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        Workspace::restrictTo($request->user()?->workspaceId());

        try {
            return $next($request);
        } finally {
            Workspace::restrictTo(null);
        }
    }
}
