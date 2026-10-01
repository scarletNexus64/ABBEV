<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Langue de la réponse : lit `Accept-Language` (envoyé par l'app
        // mobile) sur TOUTES les routes API. Placé en tête du groupe pour que
        // la locale soit posée avant l'exécution du contrôleur — sinon les
        // `__()` des validations partiraient dans la langue par défaut.
        $middleware->prependToGroup('api', \App\Http\Middleware\SetLocale::class);

        // Panel web : un producteur (et son équipe) ne voit que son espace.
        // Doit passer avant SubstituteBindings pour cloisonner aussi les
        // modèles résolus depuis l'URL.
        $middleware->appendToGroup('web', \App\Http\Middleware\ScopeToWorkspace::class);
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\ScopeToWorkspace::class,
        );

        // Espace producteur verrouillé tant que le pack producteur n'est pas
        // payé : seule la page d'abonnement reste accessible.
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureWorkspaceSubscription::class);

        $middleware->alias([
            'admin'  => \App\Http\Middleware\EnsureIsAdmin::class,
            'role'   => \App\Http\Middleware\EnsureRole::class,
            'module' => \App\Http\Middleware\EnsureModuleAccess::class,
            'locale' => \App\Http\Middleware\SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
