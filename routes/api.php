<?php

use App\Http\Controllers\Api\AdminMediaApiController;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\CategoryApiController;
use App\Http\Controllers\Api\EpisodeApiController;
use App\Http\Controllers\Api\LocaleApiController;
use App\Http\Controllers\Api\MediaApiController;
use App\Http\Controllers\Api\MyListApiController;
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

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me',         [AuthApiController::class, 'me']);
            Route::patch('/me',       [AuthApiController::class, 'updateMe']);
            Route::post('/logout',    [AuthApiController::class, 'logout']);
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
    // PROTÉGÉ — URLs de lecture (abonnement payant actif requis)
    // -------------------------------------------------------------
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/watch/movie/{movie}',     [WatchApiController::class, 'movie']);
        Route::get('/watch/episode/{episode}', [WatchApiController::class, 'episode']);
    });

    // -------------------------------------------------------------
    // PUBLIC — catégories / recherche / featured global
    // -------------------------------------------------------------
    Route::get('/categories',                       [MediaApiController::class, 'categories']);
    Route::get('/categories/{category}/media',      [MediaApiController::class, 'categoryMedia']);
    Route::get('/search',                           [MediaApiController::class, 'search']);
    Route::get('/featured',                         [MediaApiController::class, 'featured']);

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

    // Historique de paiement (paginé) + dernière transaction KPay pending.
    Route::get('/transactions', [TransactionApiController::class, 'index']);
    Route::get('/pending',      [TransactionApiController::class, 'pendingPayment']);
});

// Webhooks
Route::post('/webhooks/freemopay', [SubscriptionPaymentController::class, 'freemopayWebhook']);
