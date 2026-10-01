<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verrou de l'espace producteur : tant que le pack producteur n'est pas payé
 * (voir User::isWorkspaceLocked), le producteur et son équipe n'accèdent à
 * AUCUNE fonctionnalité du panel — seulement à la page d'abonnement.
 *
 * Appliqué à tout le groupe `web` plutôt que route par route : un module
 * ajouté plus tard est verrouillé d'office. L'admin n'est jamais concerné.
 */
class EnsureWorkspaceSubscription
{
    /** Routes ouvertes à un espace verrouillé (payer, se déconnecter, pages publiques). */
    private const ALWAYS_OPEN = [
        'producer.subscription.*',
        'admin.logout',
        'admin.login',
        'admin.login.submit',
        'admin.password.*',
        'login',
        'public.image',
        'legal.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $request->routeIs(...self::ALWAYS_OPEN) || ! $user->isWorkspaceLocked()) {
            return $next($request);
        }

        $message = $user->isProducerOwner()
            ? 'Votre espace producteur est verrouillé : abonnez-vous au pack producteur pour y accéder.'
            : "L'espace de votre producteur est verrouillé tant que son abonnement n'est pas activé.";

        // Upload chunké, pickers, actions AJAX : pas de redirection HTML.
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'subscribe_url' => route('producer.subscription.show'),
            ], 402);
        }

        return redirect()->route('producer.subscription.show');
    }
}
