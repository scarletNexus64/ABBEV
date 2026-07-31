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

        $middleware->alias([
            'admin'  => \App\Http\Middleware\EnsureIsAdmin::class,
            'role'   => \App\Http\Middleware\EnsureRole::class,
            'locale' => \App\Http\Middleware\SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
