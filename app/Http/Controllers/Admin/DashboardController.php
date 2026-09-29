<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Media;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Display dashboard
     */
    public function index()
    {
        $user       = auth()->user();
        $isProducer = $user->isProducer();

        // Contenus visibles selon le rôle (producteur = ses contenus, admin = tout)
        $media = fn () => Media::query()->visibleTo($user);

        // Users statistics — réservé aux admins
        $stats['users'] = $isProducer ? null : [
            'total' => User::count(),
            'admins' => User::where('role', 'admin')->count(),
            'users' => User::where('role', 'user')->count(),
            'users_today' => User::whereDate('created_at', today())->count(),
        ];

        // Media statistics (cloisonnées)
        $stats['media'] = [
            'total' => $media()->count(),
            'movies' => $media()->where('type', 'movie')->count(),
            'series' => $media()->where('type', 'series')->count(),
            'recent' => $media()->whereDate('created_at', '>=', Carbon::now()->subDays(7))->count(),
        ];

        // Categories statistics
        $stats['categories'] = [
            'total' => $isProducer
                ? $media()->distinct('category_id')->count('category_id')
                : Category::count(),
        ];

        // Chart data for last 30 days (cloisonné)
        $chartData = [
            'media' => [
                'labels' => $this->getLast30DaysLabels(),
                'movies' => $this->getLast30DaysData(Media::where('type', 'movie')->visibleTo($user)),
                'series' => $this->getLast30DaysData(Media::where('type', 'series')->visibleTo($user)),
            ],
            'users' => $isProducer ? null : [
                'labels' => $this->getLast30DaysLabels(),
                'data' => $this->getLast30DaysData(User::query()),
            ],
        ];

        // Top categories (cloisonnées pour le producteur)
        $topCategories = $isProducer
            ? Category::withCount(['media' => fn ($q) => $q->visibleTo($user)])
                ->orderByDesc('media_count')
                ->take(10)
                ->get()
                ->filter(fn ($c) => $c->media_count > 0)
                ->take(5)
                ->values()
            : Category::withCount('media')
                ->orderBy('media_count', 'desc')
                ->take(5)
                ->get();

        // Recent media (cloisonné)
        $recentMedia = $media()->with('category')->latest()->take(10)->get();

        // Gains du producteur (vues générées × tarif du tier) : visibles par le
        // titulaire de l'espace uniquement, pas par son équipe.
        $earnings = $user->isProducerOwner()
            ? app(\App\Services\ProducerRevenueService::class)->earningsForProducer($user)
            : null;

        // Écosystème cat.md (admin seulement) : un chiffre clé par module et
        // les actions qui attendent l'équipe.
        $ecosystem = $user->isAdmin() ? $this->ecosystem() : null;

        return view('admin.dashboard', compact('stats', 'chartData', 'topCategories', 'recentMedia', 'isProducer', 'earnings', 'ecosystem'));
    }

    /** Chiffres clés des modules Awards, casting, cours, appels, billetterie. */
    private function ecosystem(): array
    {
        $edition = \App\Models\AwardEdition::where('is_current', true)->first();
        $votes = $edition
            ? \App\Models\AwardVote::whereIn('award_category_id', $edition->categories()->pluck('id'))
            : null;

        return [
            'awards' => $edition ? [
                'edition' => $edition,
                'votes' => (clone $votes)->count(),
                'voters' => (clone $votes)->distinct('user_id')->count('user_id'),
                'today' => (clone $votes)->where('created_at', '>=', today())->count(),
            ] : null,
            'casting' => [
                'open' => \App\Models\CastingCall::acceptingApplications()->count(),
                'pending' => \App\Models\CastingApplication::where('status', 'pending')->count(),
                'week' => \App\Models\CastingApplication::where('created_at', '>=', now()->subDays(7))->count(),
            ],
            'talents' => [
                'actors' => \App\Models\Talent::where('kind', 'acteur')->count(),
                'technicians' => \App\Models\Talent::where('kind', 'technicien')->count(),
                'agents' => \App\Models\Agent::count(),
            ],
            'courses' => [
                'published' => \App\Models\Course::where('is_published', true)->count(),
                'lessons' => \App\Models\CourseLesson::count(),
            ],
            'calls' => [
                'open' => \App\Models\ProjectCall::where('status', 'open')->count(),
                'submissions' => \App\Models\ProjectSubmission::where('status', 'received')->count(),
                'pledges' => \App\Models\ProjectPledge::where('status', 'pending')->count(),
                'confirmed' => (float) \App\Models\ProjectPledge::where('status', 'confirmed')->sum('amount'),
            ],
            'tickets' => [
                'seances' => \App\Models\Screening::where('kind', 'seance')->onSale()->count(),
                'codes' => \App\Models\Screening::where('kind', 'code')->onSale()->count(),
                'sold' => \App\Models\Reservation::where('status', 'confirmed')->where('confirmed_at', '>=', now()->startOfMonth())->sum('quantity'),
            ],
            'moderation' => Media::where('moderation_status', 'pending')->count(),
        ];
    }

    /**
     * Get last 30 days labels
     */
    private function getLast30DaysLabels()
    {
        $labels = [];
        for ($i = 29; $i >= 0; $i--) {
            $labels[] = Carbon::now()->subDays($i)->format('d M');
        }
        return $labels;
    }

    /**
     * Get last 30 days data
     */
    private function getLast30DaysData($query)
    {
        $data = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->toDateString();
            $count = (clone $query)->whereDate('created_at', $date)->count();
            $data[] = $count;
        }
        return $data;
    }
}
