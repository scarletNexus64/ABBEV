<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BunnySyncController;
use App\Http\Controllers\Admin\BunnyUploadController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\EpisodeController;
use App\Http\Controllers\ProducerController;
use App\Http\Controllers\ScreeningController;
use Illuminate\Support\Facades\Route;

// Image publique (poster/cover/banner/thumbnail locale) servie AVEC CORS,
// pour l'affichage sur Flutter Web (CanvasKit). Voir PublicImageController.
Route::get('/media/img/{path}', App\Http\Controllers\PublicImageController::class)
    ->where('path', '.*')
    ->name('public.image');

// Root redirect to admin login
Route::get('/', function () {
    return redirect()->route('admin.login');
});

// Default login route (for Laravel auth redirects)
Route::get('/login', function () {
    return redirect()->route('admin.login');
})->name('login');

// Admin authentication routes
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');

    // Récupération de mot de passe (dashboard web uniquement — le mobile utilise l'OTP)
    Route::get('/forgot-password', [App\Http\Controllers\Admin\PasswordResetController::class, 'showLinkRequest'])->name('password.request');
    Route::post('/forgot-password', [App\Http\Controllers\Admin\PasswordResetController::class, 'sendResetLink'])->name('password.email');
    Route::get('/forgot-password/envoye', [App\Http\Controllers\Admin\PasswordResetController::class, 'linkSent'])->name('password.sent');
    Route::get('/reset-password/{token}', [App\Http\Controllers\Admin\PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [App\Http\Controllers\Admin\PasswordResetController::class, 'reset'])->name('password.update');

    Route::middleware(['auth', 'role:admin,producer,assistant'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    });
});

/*
|--------------------------------------------------------------------------
| Espace MODÉRATION (admin + assistant) — validation des contenus
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin,assistant'])->group(function () {
    Route::get('/moderation', [App\Http\Controllers\Admin\ModerationController::class, 'index'])->name('moderation.index');
    Route::get('/moderation/{medium}', [App\Http\Controllers\Admin\ModerationController::class, 'show'])->name('moderation.show');
    Route::post('/moderation/{medium}/approve', [App\Http\Controllers\Admin\ModerationController::class, 'approve'])->name('moderation.approve');
    Route::post('/moderation/{medium}/reject', [App\Http\Controllers\Admin\ModerationController::class, 'reject'])->name('moderation.reject');
});

/*
|--------------------------------------------------------------------------
| Espace STAFF (admin + producteur) — contenus + upload (données cloisonnées)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin,producer'])->group(function () {
    Route::resource('media', MediaController::class);

    // Episodes Management for Series
    Route::prefix('media/{media}/episodes')->name('episodes.')->group(function () {
        Route::get('/', [EpisodeController::class, 'index'])->name('index');
        Route::post('/season', [EpisodeController::class, 'createSeason'])->name('season.create');
    });

    Route::prefix('season/{season}')->name('episodes.')->group(function () {
        Route::get('/episode/create', [EpisodeController::class, 'create'])->name('create');
        Route::post('/episode', [EpisodeController::class, 'store'])->name('store');
        Route::delete('/', [EpisodeController::class, 'destroySeason'])->name('season.destroy');
    });

    Route::prefix('episode/{episode}')->name('episodes.')->group(function () {
        Route::get('/edit', [EpisodeController::class, 'edit'])->name('edit');
        Route::put('/', [EpisodeController::class, 'update'])->name('update');
        Route::delete('/', [EpisodeController::class, 'destroy'])->name('destroy');
    });

    // Films and Series (listes cloisonnées par producteur)
    Route::get('/films', [App\Http\Controllers\FilmController::class, 'index'])->name('films.index');
    Route::get('/series', [App\Http\Controllers\SerieController::class, 'index'])->name('series.index');

    // Bunny : picker + upload (cloisonnés au producteur)
    Route::prefix('admin/bunny')->name('admin.bunny.')->group(function () {
        Route::get('/videos/available',              [BunnySyncController::class, 'available'])->name('videos.available');
        Route::get('/uploads',                       [BunnyUploadController::class, 'index'])->name('uploads.index');
        Route::get('/uploads/active',                [BunnyUploadController::class, 'active'])->name('uploads.active');
        Route::post('/upload/start',                 [BunnyUploadController::class, 'start'])->name('upload.start');
        Route::match(['get', 'post'], '/upload/chunk', [BunnyUploadController::class, 'chunk'])->name('upload.chunk');
        Route::get('/uploads/{upload}/status',       [BunnyUploadController::class, 'status'])->name('uploads.status');
        Route::get('/uploads/{upload}/download',     [BunnyUploadController::class, 'download'])->name('uploads.download');
        Route::get('/uploads/{upload}/stream',       [BunnyUploadController::class, 'stream'])->name('uploads.stream');
        Route::post('/uploads/{upload}/retry',       [BunnyUploadController::class, 'retry'])->name('uploads.retry');
        Route::post('/uploads/bulk-delete',          [BunnyUploadController::class, 'bulkDestroy'])->name('uploads.bulk-delete');
        Route::delete('/uploads/{upload}',           [BunnyUploadController::class, 'destroy'])->name('uploads.destroy');
    });
});

/*
|--------------------------------------------------------------------------
| Espace ADMIN uniquement — gestion plateforme
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::resource('categories', CategoryController::class);
    // Annulation d'une séance (statut → canceled). Route hors resource.
    Route::post('screenings/{screening}/cancel', [ScreeningController::class, 'cancel'])
        ->name('screenings.cancel');
    Route::resource('screenings', ScreeningController::class);
    Route::get('/settings', [App\Http\Controllers\SettingController::class, 'index'])->name('settings.index');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    // Utilisateurs (abonnés)
    Route::get('/users', [App\Http\Controllers\UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [App\Http\Controllers\UserController::class, 'create'])->name('users.create');
    Route::post('/users', [App\Http\Controllers\UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [App\Http\Controllers\UserController::class, 'edit'])->name('users.edit');
    Route::get('/users/{user}', [App\Http\Controllers\UserController::class, 'show'])->name('users.show');
    Route::put('/users/{user}', [App\Http\Controllers\UserController::class, 'update'])->name('users.update');
    Route::patch('/users/{user}/status', [App\Http\Controllers\UserController::class, 'updateStatus'])->name('users.status');
    Route::post('/users/{user}/reset-password', [App\Http\Controllers\UserController::class, 'resetPassword'])->name('users.resetPassword');
    Route::post('/users/{user}/subscription/extend', [App\Http\Controllers\UserController::class, 'extendSubscription'])->name('users.subscription.extend');
    Route::delete('/users/{user}/subscription/{subscription}', [App\Http\Controllers\UserController::class, 'cancelSubscription'])->name('users.subscription.cancel');
    Route::delete('/users/{user}', [App\Http\Controllers\UserController::class, 'destroy'])->name('users.destroy');

    // Administrateurs
    Route::get('/administrators', [App\Http\Controllers\AdminUserController::class, 'index'])->name('administrators.index');
    Route::get('/administrators/create', [App\Http\Controllers\AdminUserController::class, 'create'])->name('administrators.create');
    Route::post('/administrators', [App\Http\Controllers\AdminUserController::class, 'store'])->name('administrators.store');
    Route::delete('/administrators/{user}', [App\Http\Controllers\AdminUserController::class, 'destroy'])->name('administrators.destroy');

    // Producteurs
    Route::get('/producers', [ProducerController::class, 'index'])->name('producers.index');
    Route::get('/producers/create', [ProducerController::class, 'create'])->name('producers.create');
    Route::post('/producers', [ProducerController::class, 'store'])->name('producers.store');
    Route::get('/producers/{user}', [ProducerController::class, 'show'])->name('producers.show');
    Route::post('/producers/{user}/resend', [ProducerController::class, 'resend'])->name('producers.resend');
    Route::delete('/producers/{user}', [ProducerController::class, 'destroy'])->name('producers.destroy');

    // Revenus producteurs (comptes dus + simulation des tarifs)
    Route::get('/earnings', [App\Http\Controllers\ProducerEarningsController::class, 'index'])->name('earnings.index');

    // Assistants (direction artistique — modération des contenus)
    Route::get('/assistants', [App\Http\Controllers\AssistantController::class, 'index'])->name('assistants.index');
    Route::get('/assistants/create', [App\Http\Controllers\AssistantController::class, 'create'])->name('assistants.create');
    Route::post('/assistants', [App\Http\Controllers\AssistantController::class, 'store'])->name('assistants.store');
    Route::post('/assistants/{user}/resend', [App\Http\Controllers\AssistantController::class, 'resend'])->name('assistants.resend');
    Route::delete('/assistants/{user}', [App\Http\Controllers\AssistantController::class, 'destroy'])->name('assistants.destroy');

    Route::resource('subscription-plans', App\Http\Controllers\SubscriptionPlanController::class);
    Route::get('/transactions', [App\Http\Controllers\TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/{transaction}', [App\Http\Controllers\TransactionController::class, 'show'])->name('transactions.show');

    Route::get('/configuration', [App\Http\Controllers\ConfigurationController::class, 'index'])->name('configuration.index');
    Route::post('/configuration', [App\Http\Controllers\ConfigurationController::class, 'update'])->name('configuration.update');
    Route::post('/configuration/kpay/test', [App\Http\Controllers\ConfigurationController::class, 'testKpay'])->name('configuration.testKpay');
    Route::post('/configuration/mail/test', [App\Http\Controllers\ConfigurationController::class, 'testMail'])->name('configuration.testMail');
    Route::post('/configuration/bunny/test', [App\Http\Controllers\ConfigurationController::class, 'testBunny'])->name('configuration.testBunny');
    Route::post('/configuration/{group}', [App\Http\Controllers\ConfigurationController::class, 'updateGroup'])->name('configuration.updateGroup');

    // Bunny Library complète (toutes les vidéos) — admin only
    Route::prefix('bunny')->name('admin.bunny.')->group(function () {
        Route::get('/library',  [BunnySyncController::class, 'library'])->name('library');
        Route::post('/refresh', [BunnySyncController::class, 'refresh'])->name('refresh');
    });
});
