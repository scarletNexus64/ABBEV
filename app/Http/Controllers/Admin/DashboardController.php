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

        // Gains du producteur (vues générées × tarif du tier).
        $earnings = $isProducer
            ? app(\App\Services\ProducerRevenueService::class)->earningsForProducer($user)
            : null;

        return view('admin.dashboard', compact('stats', 'chartData', 'topCategories', 'recentMedia', 'isProducer', 'earnings'));
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
