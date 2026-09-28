<?php

use App\Http\Controllers\Api\AdminMediaApiController;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\AwardApiController;
use App\Http\Controllers\Api\CastingApiController;
use App\Http\Controllers\Api\CategoryApiController;
use App\Http\Controllers\Api\CourseApiController;
use App\Http\Controllers\Api\CryptoPaymentController;
use App\Http\Controllers\Api\EpisodeApiController;
use App\Http\Controllers\Api\ExploreApiController;
use App\Http\Controllers\Api\LocaleApiController;
use App\Http\Controllers\Api\ProjectCallApiController;
use App\Http\Controllers\Api\TalentApiController;
use App\Http\Controllers\Api\LocalVideoStreamController;
use App\Http\Controllers\Api\OeuvreFileController;
use App\Http\Controllers\Api\MediaApiController;
use App\Http\Controllers\Api\MyListApiController;
use App\Http\Controllers\Api\ReservationPaymentController;
use App\Http\Controllers\Api\RubriqueApiController;
use App\Http\Controllers\Api\ScreeningApiController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\SubscriptionPaymentController;
use App\Http\Controllers\Api\SubscriptionPlanApiController;
use App\Http\Controllers\Api\TransactionApiController;
use App\Http\Controllers\Api\WatchApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // -------------------------------------------------------------
    // Auth (Sanctum tokens)
    // -------------------------------------------------------------
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthApiController::class, 'register']);
        Route::post('/login',    [AuthApiController::class, 'login']);

        // Login / inscription par OTP email
        Route::post('/send-otp',   [AuthApiController::class, 'sendOtp']);
        Route::post('/verify-otp', [AuthApiController::class, 'verifyOtp']);

        // Mot de passe oublié : code par email → vérification → nouveau mot de passe.
        Route::post('/forgot-password',   [AuthApiController::class, 'forgotPassword'])
            ->middleware('throttle:6,1');
        Route::post('/verify-reset-code', [AuthApiController::class, 'verifyResetCode']);
        Route::post('/reset-password',    [AuthApiController::class, 'resetPassword']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me',         [AuthApiController::class, 'me']);
            Route::patch('/me',       [AuthApiController::class, 'updateMe']);
            Route::post('/logout',    [AuthApiController::class, 'logout']);
            // Suppression définitive du compte — exigée par l'App Store
            // (Guideline 5.1.1(v)) pour toute app à création de compte.
            Route::delete('/me',      [AuthApiController::class, 'deleteAccount']);
            Route::get('/me/stats',   [AuthApiController::class, 'stats']);
            Route::get('/me/subscription', [AuthApiController::class, 'currentSubscription']);
            Route::post('/watch-history', [AuthApiController::class, 'recordWatch']);

            // Ma liste (films + séries confondus)
            Route::get('/my-list',                  [MyListApiController::class, 'index']);
            Route::post('/my-list',                 [MyListApiController::class, 'store']);
            Route::delete('/my-list/{media}',       [MyListApiController::class, 'destroy']);
            Route::get('/my-list/{media}/status',   [MyListApiController::class, 'status']);
        });
    });

    // -------------------------------------------------------------
    // PUBLIC — films
    // -------------------------------------------------------------
    Route::prefix('movies')->group(function () {
        Route::get('/',              [MediaApiController::class, 'movies']);
        Route::get('/popular',       [MediaApiController::class, 'popularMovies']);
        Route::get('/trending',      [MediaApiController::class, 'trendingMovies']);
        Route::get('/new-releases',  [MediaApiController::class, 'newReleases']);
        Route::get('/featured',      [MediaApiController::class, 'featuredMovies']);
        Route::get('/by-category/{category}', [MediaApiController::class, 'moviesByCategory']);
        Route::get('/{movie}',       [MediaApiController::class, 'movieShow']);
    });

    // -------------------------------------------------------------
    // PUBLIC — séries
    // -------------------------------------------------------------
    Route::prefix('series')->group(function () {
        Route::get('/',          [MediaApiController::class, 'series']);
        Route::get('/popular',   [MediaApiController::class, 'popularSeries']);
        Route::get('/featured',  [MediaApiController::class, 'featuredSeries']);
        Route::get('/by-category/{category}', [MediaApiController::class, 'seriesByCategory']);
        Route::get('/{series}',  [MediaApiController::class, 'serieShow']);
        Route::get('/{series}/seasons', [EpisodeApiController::class, 'seasonsOfMedia']);
    });

    Route::get('/episodes/{episode}', [EpisodeApiController::class, 'show']);

    // -------------------------------------------------------------
    // PUBLIC — plans d'abonnement
    // -------------------------------------------------------------
    Route::get('/subscription-plans',                    [SubscriptionPlanApiController::class, 'index']);
    Route::get('/subscription-plans/{subscriptionPlan}', [SubscriptionPlanApiController::class, 'show']);

    // -------------------------------------------------------------
    // PUBLIC — locales (pays + devises pour l'inscription)
    // -------------------------------------------------------------
    Route::get('/countries',  [LocaleApiController::class, 'countries']);
    Route::get('/currencies', [LocaleApiController::class, 'currencies']);

    // -------------------------------------------------------------
    // Séances cinéma + réservation de tickets
    // -------------------------------------------------------------
    // Public : consultation des séances réservables.
    Route::get('/screenings',            [ScreeningApiController::class, 'index']);
    Route::get('/screenings/{screening}', [ScreeningApiController::class, 'show']);

    // Protégé : réservation + paiement + gestion de ses réservations.
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/reservations',                         [ScreeningApiController::class, 'reserve']);
        Route::get('/reservations',                          [ScreeningApiController::class, 'myReservations']);
        Route::post('/reservations/{reservation}/confirm',   [ScreeningApiController::class, 'confirm']);
        Route::post('/reservations/{reservation}/cancel',    [ScreeningApiController::class, 'cancel']);
    });

    // -------------------------------------------------------------
    // PROTÉGÉ — URLs de lecture (abonnement payant actif requis)
    // -------------------------------------------------------------
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/watch/movie/{movie}',     [WatchApiController::class, 'movie']);
        Route::get('/watch/episode/{episode}', [WatchApiController::class, 'episode']);

        // Téléchargement offline : URL MP4 signée à durée de vie courte.
        // Throttle : 30 demandes / heure / utilisateur — large pour un
        // usage normal (binge offline d'une série) mais coupe net une
        // énumération automatisée du catalogue.
        Route::middleware('throttle:30,60')->group(function () {
            Route::get(
                '/watch/movie/{movie}/download',
                [WatchApiController::class, 'movieDownload']
            );
            Route::get(
                '/watch/episode/{episode}/download',
                [WatchApiController::class, 'episodeDownload']
            );
        });
    });

    // Streaming des vidéos LOCALES via URL SIGNÉE (générée par /watch après
    // vérification de l'abonnement). La signature (+ expiration) EST le
    // contrôle d'accès — plus de lien public permanent partageable.
    Route::get('/watch/local/{type}/{id}', LocalVideoStreamController::class)
        ->middleware('signed')
        ->where('type', 'movie|episode')
        ->name('api.watch.local');

    // -------------------------------------------------------------
    // PUBLIC — rubriques thématiques (chips de l'accueil mobile)
    // -------------------------------------------------------------
    // Volontairement hors `auth:sanctum` : un visiteur non connecté doit voir
    // les rubriques ouvertes. Le contrôleur résout lui-même l'utilisateur via
    // le guard sanctum pour filtrer celles qui exigent un abonnement.
    Route::get('/rubriques',                       [RubriqueApiController::class, 'index']);
    Route::get('/rubriques/{rubrique}/contents',   [RubriqueApiController::class, 'contents']);

    // PDF d'une oeuvre — URL signee temporaire (pas besoin de header Authorization).
    Route::get('/oeuvres/{oeuvre}/file', OeuvreFileController::class)
        ->middleware('signed')
        ->name('api.oeuvres.file');

    // -------------------------------------------------------------
    // PUBLIC — catégories / recherche / featured global
    // -------------------------------------------------------------
    Route::get('/categories',                       [MediaApiController::class, 'categories']);
    Route::get('/categories/{category}/media',      [MediaApiController::class, 'categoryMedia']);
    Route::get('/search',                           [MediaApiController::class, 'search']);
    Route::get('/featured',                         [MediaApiController::class, 'featured']);

    // -------------------------------------------------------------
    // EXPLORER — sections de cat.md (sommaire, awards, talents,
    // casting, cours, appels à projets). Consultation publique ;
    // toute participation exige un compte.
    // -------------------------------------------------------------
    Route::get('/explore', ExploreApiController::class);

    // Lions Head Awards
    Route::get('/awards/current',            [AwardApiController::class, 'current']);
    Route::get('/awards/editions/{edition}', [AwardApiController::class, 'show']);
    Route::post('/awards/nominees/{nominee}/vote', [AwardApiController::class, 'vote'])
        ->middleware(['auth:sanctum', 'throttle:60,1']);

    // Talents & agents
    Route::get('/talents',          [TalentApiController::class, 'index']);
    Route::get('/talents/{talent}', [TalentApiController::class, 'show']);
    Route::get('/agents',           [TalentApiController::class, 'agents']);
    Route::get('/agents/{agent}',   [TalentApiController::class, 'agent']);

    // Annonces de casting
    Route::get('/casting-calls',        [CastingApiController::class, 'index']);
    Route::get('/casting-calls/{call}', [CastingApiController::class, 'show']);
    Route::post('/casting-roles/{role}/apply', [CastingApiController::class, 'apply'])
        ->middleware(['auth:sanctum', 'throttle:10,1']);

    // Cours de cinéma
    Route::get('/courses',          [CourseApiController::class, 'index']);
    Route::get('/courses/{course}', [CourseApiController::class, 'show']);
    Route::get('/courses/{course}/lessons/{lesson}/access', [CourseApiController::class, 'access'])
        ->middleware(['auth:sanctum', 'throttle:60,1']);
    // PDF d'une leçon — URL signée délivrée par `access` (pas d'en-tête requis).
    Route::get('/course-lessons/{lesson}/file', [CourseApiController::class, 'file'])
        ->middleware('signed')
        ->name('api.course-lessons.file');

    // Appels à projets (financement, écriture, musique)
    Route::get('/calls',        [ProjectCallApiController::class, 'index']);
    Route::get('/calls/{call}', [ProjectCallApiController::class, 'show']);
    Route::middleware(['auth:sanctum', 'throttle:10,1'])->group(function () {
        Route::post('/calls/{call}/submissions', [ProjectCallApiController::class, 'submit']);
        Route::post('/calls/{call}/pledges',     [ProjectCallApiController::class, 'pledge']);
    });

    // Mes participations (candidatures casting, appels, promesses)
    Route::middleware('auth:sanctum')->prefix('me')->group(function () {
        Route::get('/casting-applications', [CastingApiController::class, 'mine']);
        Route::get('/calls',                [ProjectCallApiController::class, 'mine']);
    });

    // -------------------------------------------------------------
    // PUBLIC — compat ancienne route
    // -------------------------------------------------------------
    Route::get('/media',                 [MediaApiController::class, 'index']);
    Route::get('/media/featured',        [MediaApiController::class, 'featured']);
    Route::get('/media/{slug}',          [MediaApiController::class, 'show']);

    // -------------------------------------------------------------
    // ADMIN — upload chunké + CRUD complet
    // -------------------------------------------------------------
    Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->name('api.admin.')->group(function () {

        // (upload chunk supprimé — utiliser le dashboard Bunny pour uploader,
        //  puis attribuer le video_id à un Media/Episode via les endpoints CRUD)

        // Categories
        Route::apiResource('categories', CategoryApiController::class);

        // Media (movies + series)
        Route::apiResource('media', AdminMediaApiController::class);

        // Saisons / Épisodes
        Route::post('/series/{media}/seasons',     [EpisodeApiController::class, 'storeSeason']);
        Route::delete('/seasons/{season}',         [EpisodeApiController::class, 'destroySeason']);
        Route::post('/seasons/{season}/episodes',  [EpisodeApiController::class, 'storeEpisode']);
        Route::put('/episodes/{episode}',          [EpisodeApiController::class, 'update']);
        Route::delete('/episodes/{episode}',       [EpisodeApiController::class, 'destroy']);
    });
});

// -------------------------------------------------------------
// Paiement des abonnements (existant)
// -------------------------------------------------------------
Route::middleware('auth:sanctum')->prefix('subscription-payment')->group(function () {
    Route::post('/initiate', [SubscriptionPaymentController::class, 'initiate']);
    Route::post('/paypal/capture', [SubscriptionPaymentController::class, 'capturePayPal']);
    Route::get('/freemopay/status/{reference}', [SubscriptionPaymentController::class, 'checkFreeMoPayStatus']);

    // KPay — paiement
    Route::get('/kpay/status/{reference}', [SubscriptionPaymentController::class, 'checkKpayStatus']);
    // KPay — pays & opérateurs supportés (pour le sélecteur mobile).
    Route::get('/kpay/countries', [SubscriptionPaymentController::class, 'kpayCountries']);

    // Apple In-App Purchase (iOS) — vérification d'un achat StoreKit.
    Route::post('/apple/verify', [SubscriptionPaymentController::class, 'verifyApple']);

    // Stripe (carte — abonnement, Android uniquement côté app).
    Route::post('/stripe/confirm', [SubscriptionPaymentController::class, 'confirmStripe']);

    // Historique de paiement (paginé) + dernière transaction KPay pending.
    Route::get('/transactions', [TransactionApiController::class, 'index']);
    Route::get('/pending',      [TransactionApiController::class, 'pendingPayment']);
});

// -------------------------------------------------------------
// Paiement des réservations de tickets (PayPal / KPay)
// -------------------------------------------------------------
Route::middleware('auth:sanctum')->prefix('reservation-payment')->group(function () {
    Route::post('/initiate',                   [ReservationPaymentController::class, 'initiate']);
    Route::post('/paypal/capture',             [ReservationPaymentController::class, 'capturePayPal']);
    Route::post('/stripe/confirm',             [ReservationPaymentController::class, 'confirmStripe']);
    Route::get('/kpay/status/{reference}',      [ReservationPaymentController::class, 'checkKpayStatus']);
});

// -------------------------------------------------------------
// Paiement par crypto-monnaie (BTC, ETH, USDT…) via NOWPayments
// Couvre abonnements ET réservations de tickets (champ `purpose`).
// -------------------------------------------------------------
Route::get('/crypto-payment/config', [CryptoPaymentController::class, 'config']);
Route::middleware('auth:sanctum')->prefix('crypto-payment')->group(function () {
    Route::post('/initiate',             [CryptoPaymentController::class, 'initiate']);
    Route::get('/status/{transactionId}', [CryptoPaymentController::class, 'status']);
});

// -------------------------------------------------------------
// Config publique Stripe (clé publishable pour init du SDK mobile)
// -------------------------------------------------------------
Route::get('/payments/stripe/config', function (\App\Services\StripeService $stripe) {
    return response()->json([
        'enabled'         => $stripe->isConfigured(),
        'publishable_key' => $stripe->publishableKey(),
    ]);
});

// Webhooks
Route::post('/webhooks/freemopay', [SubscriptionPaymentController::class, 'freemopayWebhook']);
// App Store Server Notifications v2 (renouvellements/expirations/refunds Apple).
Route::post('/webhooks/apple', [SubscriptionPaymentController::class, 'appleWebhook']);
// Stripe — source de vérité des paiements carte (payment_intent.succeeded).
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle']);
// NOWPayments — IPN crypto (signé HMAC-SHA512, header x-nowpayments-sig).
Route::post('/webhooks/nowpayments', [CryptoPaymentController::class, 'webhook']);
