<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BunnySyncController;
use App\Http\Controllers\Admin\BunnyUploadController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\EpisodeController;
use App\Http\Controllers\ProducerController;
use App\Http\Controllers\OeuvreController;
use App\Http\Controllers\ScreeningController;
use Illuminate\Support\Facades\Route;

// Image publique (poster/cover/banner/thumbnail locale) servie AVEC CORS,
// pour l'affichage sur Flutter Web (CanvasKit). Voir PublicImageController.
Route::get('/media/img/{path}', App\Http\Controllers\PublicImageController::class)
    ->where('path', '.*')
    ->name('public.image');

// Pages légales publiques (aucune authentification). Liées depuis l'écran
// d'abonnement de l'app et vérifiées par le reviewer Apple : les URLs ne
// doivent plus changer une fois l'app soumise.
Route::get('/conditions-utilisation', [App\Http\Controllers\LegalController::class, 'terms'])
    ->name('legal.terms');
Route::get('/confidentialite', [App\Http\Controllers\LegalController::class, 'privacy'])
    ->name('legal.privacy');

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

    Route::middleware(['auth', 'role:admin,producer'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    });
});

/*
|--------------------------------------------------------------------------
| Espace PRODUCTEUR — chaque module est cloisonné à l'espace du producteur
| (ScopeToWorkspace) et ouvert à son équipe module par module (`module:`).
| L'admin a accès à tout, tous espaces confondus.
|--------------------------------------------------------------------------
*/

// Modération des contenus de l'espace
Route::middleware(['auth', 'role:admin,producer', 'module:moderation'])->group(function () {
    Route::get('/moderation', [App\Http\Controllers\Admin\ModerationController::class, 'index'])->name('moderation.index');
    Route::get('/moderation/{medium}', [App\Http\Controllers\Admin\ModerationController::class, 'show'])->name('moderation.show');
    Route::post('/moderation/{medium}/approve', [App\Http\Controllers\Admin\ModerationController::class, 'approve'])->name('moderation.approve');
    Route::post('/moderation/{medium}/reject', [App\Http\Controllers\Admin\ModerationController::class, 'reject'])->name('moderation.reject');
});

// Audience : qui a regardé les contenus de l'espace
Route::middleware(['auth', 'role:admin,producer', 'module:audience'])->group(function () {
    Route::get('/audience', [App\Http\Controllers\Admin\AudienceController::class, 'index'])->name('audience.index');
});

// Abonnement au pack producteur : seule page ouverte à un espace verrouillé
// (EnsureWorkspaceSubscription). Le titulaire y paie par Mobile Money ou carte.
Route::middleware(['auth', 'role:producer'])->prefix('admin/mon-abonnement')->name('producer.subscription.')->group(function () {
    Route::get('/', [App\Http\Controllers\Admin\ProducerSubscriptionController::class, 'show'])->name('show');
    Route::post('/kpay', [App\Http\Controllers\Admin\ProducerSubscriptionController::class, 'payKpay'])->middleware('throttle:10,1')->name('kpay');
    Route::post('/stripe', [App\Http\Controllers\Admin\ProducerSubscriptionController::class, 'payStripe'])->middleware('throttle:10,1')->name('stripe');
    Route::get('/paiements/{transaction}', [App\Http\Controllers\Admin\ProducerSubscriptionController::class, 'status'])->name('status');
});

// Équipe du producteur (réservée au producteur titulaire de l'espace)
Route::middleware(['auth', 'role:producer'])->prefix('admin/team')->name('team.')->group(function () {
    Route::get('/', [App\Http\Controllers\Admin\TeamController::class, 'index'])->name('index');
    Route::get('/create', [App\Http\Controllers\Admin\TeamController::class, 'create'])->name('create');
    Route::post('/', [App\Http\Controllers\Admin\TeamController::class, 'store'])->name('store');
    Route::get('/{member}/edit', [App\Http\Controllers\Admin\TeamController::class, 'edit'])->name('edit');
    Route::put('/{member}', [App\Http\Controllers\Admin\TeamController::class, 'update'])->name('update');
    Route::post('/{member}/resend', [App\Http\Controllers\Admin\TeamController::class, 'resend'])->name('resend');
    Route::delete('/{member}', [App\Http\Controllers\Admin\TeamController::class, 'destroy'])->name('destroy');
});

// Œuvres adaptables
Route::middleware(['auth', 'role:admin,producer', 'module:oeuvres'])->group(function () {
    Route::resource('oeuvres', OeuvreController::class)->except(['show']);
});

// Films, séries, épisodes et upload vidéos
Route::middleware(['auth', 'role:admin,producer', 'module:contents'])->group(function () {
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
    // Genres (cat.md : 15 genres) — noms de routes `categories.*` conservés.
    Route::resource('categories', CategoryController::class)->except(['show']);
    Route::post('categories/reorder', [CategoryController::class, 'reorder'])->name('categories.reorder');
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
    Route::post('/producers/{user}/access', [ProducerController::class, 'grantAccess'])->name('producers.access.grant');
    Route::delete('/producers/{user}/access', [ProducerController::class, 'revokeAccess'])->name('producers.access.revoke');

    // Pack producteur (un seul) : prix et période de l'abonnement des producteurs
    Route::get('/producer-plan', [App\Http\Controllers\Admin\ProducerPlanController::class, 'edit'])->name('producer-plan.edit');
    Route::put('/producer-plan', [App\Http\Controllers\Admin\ProducerPlanController::class, 'update'])->name('producer-plan.update');

    // Transfert de données (plateforme ou producteur → producteur)
    Route::get('/transfers', [App\Http\Controllers\Admin\DataTransferController::class, 'index'])->name('transfers.index');
    Route::post('/transfers', [App\Http\Controllers\Admin\DataTransferController::class, 'store'])->name('transfers.store');

    // Revenus producteurs (comptes dus + simulation des tarifs)
    Route::get('/earnings', [App\Http\Controllers\ProducerEarningsController::class, 'index'])->name('earnings.index');

    Route::resource('subscription-plans', App\Http\Controllers\SubscriptionPlanController::class)->except(['show']);
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

/*
|--------------------------------------------------------------------------
| Sélections éditoriales (admin) — vitrine de l'app, tous producteurs
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    // Sélections éditoriales (À la une / Avant-première / Sport / Jeux)
    Route::get('/rubriques', [App\Http\Controllers\Admin\RubriqueController::class, 'index'])->name('rubriques.index');
    Route::get('/rubriques/a-la-une', [App\Http\Controllers\Admin\RubriqueController::class, 'featured'])->name('rubriques.featured');
    Route::get('/rubriques/{rubrique}/edit', [App\Http\Controllers\Admin\RubriqueController::class, 'edit'])->name('rubriques.edit');
    Route::put('/rubriques/{rubrique}', [App\Http\Controllers\Admin\RubriqueController::class, 'update'])->name('rubriques.update');
    Route::post('/rubriques/{rubrique}/media', [App\Http\Controllers\Admin\RubriqueController::class, 'attach'])->name('rubriques.media.attach');
    Route::delete('/rubriques/{rubrique}/media/{media}', [App\Http\Controllers\Admin\RubriqueController::class, 'detach'])->name('rubriques.media.detach');
    Route::post('/rubriques/{rubrique}/media/{media}/move', [App\Http\Controllers\Admin\RubriqueController::class, 'move'])->name('rubriques.media.move');
    Route::post('/media/{media}/featured', [App\Http\Controllers\Admin\RubriqueController::class, 'toggleFeatured'])->name('rubriques.featured.toggle');

    // Édition des Awards affichée dans l'app : une seule, tous producteurs confondus
    Route::post('/awards/{edition}/current', [App\Http\Controllers\Admin\AwardEditionController::class, 'makeCurrent'])->name('awards.current');
});

/*
|--------------------------------------------------------------------------
| Modules cat.md (espace producteur) — talents & casting, Lions Head Awards,
| cours, appels à projets, billetterie et contrôle des billets
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin,producer', 'module:talents'])->prefix('admin')->group(function () {
    // Talents & agents
    Route::resource('talents', App\Http\Controllers\Admin\TalentController::class)->except(['show']);
    Route::resource('agents', App\Http\Controllers\Admin\AgentController::class)->except(['show']);

    // Annonces de casting et candidatures
    Route::resource('castings', App\Http\Controllers\Admin\CastingCallController::class)
        ->parameters(['castings' => 'casting']);
    Route::patch('/castings/{casting}/applications/{application}', [App\Http\Controllers\Admin\CastingCallController::class, 'review'])
        ->name('castings.applications.review');
    Route::get('/castings/{casting}/applications/{application}/photo', [App\Http\Controllers\Admin\CastingCallController::class, 'photo'])
        ->name('castings.applications.photo');
    Route::get('/castings/{casting}/export', [App\Http\Controllers\Admin\CastingCallController::class, 'export'])
        ->name('castings.export');
});

Route::middleware(['auth', 'role:admin,producer', 'module:awards'])->prefix('admin')->group(function () {
    Route::resource('awards', App\Http\Controllers\Admin\AwardEditionController::class)
        ->parameters(['awards' => 'edition']);
    Route::post('/awards/{edition}/publish', [App\Http\Controllers\Admin\AwardEditionController::class, 'publish'])->name('awards.publish');
    Route::post('/awards/{edition}/unpublish', [App\Http\Controllers\Admin\AwardEditionController::class, 'unpublish'])->name('awards.unpublish');
    Route::post('/awards/{edition}/template', [App\Http\Controllers\Admin\AwardEditionController::class, 'applyTemplate'])->name('awards.template');
    Route::post('/awards/{edition}/categories', [App\Http\Controllers\Admin\AwardCategoryController::class, 'store'])->name('awards.categories.store');
    Route::put('/award-categories/{category}', [App\Http\Controllers\Admin\AwardCategoryController::class, 'update'])->name('awards.categories.update');
    Route::delete('/award-categories/{category}', [App\Http\Controllers\Admin\AwardCategoryController::class, 'destroy'])->name('awards.categories.destroy');
    Route::post('/award-categories/{category}/nominees', [App\Http\Controllers\Admin\AwardCategoryController::class, 'storeNominee'])->name('awards.nominees.store');
    Route::delete('/award-nominees/{nominee}', [App\Http\Controllers\Admin\AwardCategoryController::class, 'destroyNominee'])->name('awards.nominees.destroy');
    Route::post('/award-nominees/{nominee}/winner', [App\Http\Controllers\Admin\AwardCategoryController::class, 'toggleWinner'])->name('awards.nominees.winner');
});

Route::middleware(['auth', 'role:admin,producer', 'module:courses'])->prefix('admin')->group(function () {
    Route::resource('courses', App\Http\Controllers\Admin\CourseController::class)->except(['show']);
    Route::post('/courses/{course}/lessons', [App\Http\Controllers\Admin\CourseController::class, 'storeLesson'])->name('courses.lessons.store');
    Route::put('/course-lessons/{lesson}', [App\Http\Controllers\Admin\CourseController::class, 'updateLesson'])->name('courses.lessons.update');
    Route::delete('/course-lessons/{lesson}', [App\Http\Controllers\Admin\CourseController::class, 'destroyLesson'])->name('courses.lessons.destroy');
    Route::post('/course-lessons/{lesson}/move', [App\Http\Controllers\Admin\CourseController::class, 'moveLesson'])->name('courses.lessons.move');
    Route::get('/course-lessons/{lesson}/file', [App\Http\Controllers\Admin\CourseController::class, 'lessonFile'])->name('courses.lessons.file');
});

Route::middleware(['auth', 'role:admin,producer', 'module:calls'])->prefix('admin')->group(function () {
    // Financement, écriture, musique
    Route::resource('calls', App\Http\Controllers\Admin\ProjectCallController::class);
    Route::patch('/calls/{call}/submissions/{submission}', [App\Http\Controllers\Admin\ProjectCallController::class, 'reviewSubmission'])
        ->name('calls.submissions.review');
    Route::get('/calls/{call}/submissions/{submission}/file', [App\Http\Controllers\Admin\ProjectCallController::class, 'submissionFile'])
        ->name('calls.submissions.file');
    Route::patch('/calls/{call}/pledges/{pledge}', [App\Http\Controllers\Admin\ProjectCallController::class, 'reviewPledge'])
        ->name('calls.pledges.review');
    Route::get('/calls/{call}/export', [App\Http\Controllers\Admin\ProjectCallController::class, 'export'])
        ->name('calls.export');
});

// Séances & codes cinéma (URLs historiques hors /admin conservées)
Route::middleware(['auth', 'role:admin,producer', 'module:ticketing'])->group(function () {
    // Annulation d'une séance (statut → canceled). Route hors resource.
    Route::post('screenings/{screening}/cancel', [ScreeningController::class, 'cancel'])
        ->name('screenings.cancel');
    Route::resource('screenings', ScreeningController::class)->except(['show']);
});

Route::middleware(['auth', 'role:admin,producer', 'module:tickets'])->prefix('admin')->group(function () {
    Route::get('/tickets/check', [App\Http\Controllers\Admin\TicketCheckController::class, 'index'])->name('tickets.check');
    Route::post('/tickets/{reservation}/redeem', [App\Http\Controllers\Admin\TicketCheckController::class, 'redeem'])->name('tickets.redeem');
});
