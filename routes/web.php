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

    Route::middleware(['auth', 'role:admin,producer'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    });
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
    Route::resource('screenings', ScreeningController::class);
    Route::get('/settings', [App\Http\Controllers\SettingController::class, 'index'])->name('settings.index');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    // Utilisateurs (abonnés)
    Route::get('/users', [App\Http\Controllers\UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [App\Http\Controllers\UserController::class, 'show'])->name('users.show');
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
    Route::delete('/producers/{user}', [ProducerController::class, 'destroy'])->name('producers.destroy');

    Route::resource('subscription-plans', App\Http\Controllers\SubscriptionPlanController::class);
    Route::get('/transactions', [App\Http\Controllers\TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/{transaction}', [App\Http\Controllers\TransactionController::class, 'show'])->name('transactions.show');

    Route::get('/configuration', [App\Http\Controllers\ConfigurationController::class, 'index'])->name('configuration.index');
    Route::post('/configuration', [App\Http\Controllers\ConfigurationController::class, 'update'])->name('configuration.update');
    Route::post('/configuration/{group}', [App\Http\Controllers\ConfigurationController::class, 'updateGroup'])->name('configuration.updateGroup');

    // Bunny Library complète (toutes les vidéos) — admin only
    Route::prefix('bunny')->name('admin.bunny.')->group(function () {
        Route::get('/library',  [BunnySyncController::class, 'library'])->name('library');
        Route::post('/refresh', [BunnySyncController::class, 'refresh'])->name('refresh');
    });
});
