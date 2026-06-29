<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BunnySyncController;
use App\Http\Controllers\Admin\BunnyUploadController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\EpisodeController;
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

    Route::middleware('auth')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    });
});

// Protected routes (require authentication)
Route::middleware('auth')->group(function () {
    Route::resource('categories', CategoryController::class);
    Route::resource('media', MediaController::class);

    // (upload local supprimé — les vidéos viennent de Bunny Stream)

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

    // Films and Series
    Route::get('/films', [App\Http\Controllers\FilmController::class, 'index'])->name('films.index');
    Route::get('/series', [App\Http\Controllers\SerieController::class, 'index'])->name('series.index');

    // Settings
    Route::get('/settings', [App\Http\Controllers\SettingController::class, 'index'])->name('settings.index');
});

// Users Management
Route::middleware('auth')->prefix('admin')->group(function () {
    Route::get('/users', [App\Http\Controllers\UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [App\Http\Controllers\UserController::class, 'show'])->name('users.show');
    Route::delete('/users/{user}', [App\Http\Controllers\UserController::class, 'destroy'])->name('users.destroy');
    
    Route::get('/administrators', [App\Http\Controllers\AdminUserController::class, 'index'])->name('administrators.index');
    Route::get('/administrators/create', [App\Http\Controllers\AdminUserController::class, 'create'])->name('administrators.create');
    Route::post('/administrators', [App\Http\Controllers\AdminUserController::class, 'store'])->name('administrators.store');
    Route::delete('/administrators/{user}', [App\Http\Controllers\AdminUserController::class, 'destroy'])->name('administrators.destroy');
    
    Route::resource('subscription-plans', App\Http\Controllers\SubscriptionPlanController::class);
    Route::get('/transactions', [App\Http\Controllers\TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/{transaction}', [App\Http\Controllers\TransactionController::class, 'show'])->name('transactions.show');
    
    Route::get('/configuration', [App\Http\Controllers\ConfigurationController::class, 'index'])->name('configuration.index');
    Route::post('/configuration', [App\Http\Controllers\ConfigurationController::class, 'update'])->name('configuration.update');
    Route::post('/configuration/{group}', [App\Http\Controllers\ConfigurationController::class, 'updateGroup'])->name('configuration.updateGroup');

    // Bunny Stream (info read-only + picker)
    Route::prefix('bunny')->name('admin.bunny.')->group(function () {
        Route::get('/library',             [BunnySyncController::class, 'library'])->name('library');
        Route::get('/videos/available',    [BunnySyncController::class, 'available'])->name('videos.available');
        Route::post('/refresh',            [BunnySyncController::class, 'refresh'])->name('refresh');

        // Upload de vidéos vers la Bunny Library (async, autonome)
        Route::get('/uploads',                       [BunnyUploadController::class, 'index'])->name('uploads.index');
        Route::get('/uploads/active',                [BunnyUploadController::class, 'active'])->name('uploads.active');
        Route::post('/upload/start',                 [BunnyUploadController::class, 'start'])->name('upload.start');
        Route::match(['get', 'post'], '/upload/chunk', [BunnyUploadController::class, 'chunk'])->name('upload.chunk');
        Route::get('/uploads/{upload}/status',       [BunnyUploadController::class, 'status'])->name('uploads.status');
        Route::get('/uploads/{upload}/download',     [BunnyUploadController::class, 'download'])->name('uploads.download');
        Route::post('/uploads/{upload}/retry',       [BunnyUploadController::class, 'retry'])->name('uploads.retry');
        Route::post('/uploads/{upload}/use-local',   [BunnyUploadController::class, 'useLocal'])->name('uploads.use-local');
    });
});
