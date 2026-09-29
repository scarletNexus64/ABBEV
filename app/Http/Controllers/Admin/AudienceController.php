<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\WatchHistory;
use Illuminate\Http\Request;

/**
 * Audience : qui a regardé les contenus de l'espace, quoi et combien de temps.
 *
 * Source : `watch_history` (une ligne par session de visionnage). Le
 * cloisonnement passe par `whereHas('media')` : le scope `workspace` de Media
 * ne garde que les contenus du producteur (l'admin voit toute la plateforme).
 *
 * Les spectateurs sont des abonnés de l'app : seuls leur nom et leur pays sont
 * montrés, jamais leur email ni leur téléphone.
 */
class AudienceController extends Controller
{
    private const PERIODS = [
        '7' => '7 derniers jours',
        '30' => '30 derniers jours',
        '90' => '90 derniers jours',
        'all' => 'Depuis le début',
    ];

    public function index(Request $request)
    {
        $period = array_key_exists((string) $request->query('period'), self::PERIODS)
            ? (string) $request->query('period') : '30';
        $mediaId = $request->integer('media') ?: null;

        $contents = Media::orderBy('title')->get(['id', 'title', 'type']);
        if ($mediaId && ! $contents->contains('id', $mediaId)) {
            $mediaId = null; // contenu d'un autre espace ou inexistant : filtre ignoré
        }

        $sessions = fn () => WatchHistory::query()
            ->whereHas('media')
            ->when($mediaId, fn ($q) => $q->where('media_id', $mediaId))
            ->when($period !== 'all', fn ($q) => $q->where('created_at', '>=', now()->subDays((int) $period)));

        $totals = $sessions()
            ->selectRaw('COUNT(DISTINCT user_id) as viewers, COUNT(*) as sessions, COALESCE(SUM(watched_seconds), 0) as seconds')
            ->first();

        $stats = [
            'viewers' => (int) $totals->viewers,
            'sessions' => (int) $totals->sessions,
            'hours' => round(((int) $totals->seconds) / 3600, 1),
            // Compteur cumulé des lectures (toutes périodes confondues).
            'views' => (int) Media::when($mediaId, fn ($q) => $q->whereKey($mediaId))->sum('views_count'),
        ];

        $topContents = $sessions()
            ->select('media_id')
            ->selectRaw('COUNT(DISTINCT user_id) as viewers, COALESCE(SUM(watched_seconds), 0) as seconds, MAX(created_at) as last_seen')
            ->groupBy('media_id')
            ->orderByDesc('viewers')
            ->with('media:id,title,type,thumbnail_path,cover_path,views_count')
            ->take(10)
            ->get();

        $viewers = $sessions()
            ->select('user_id')
            ->selectRaw('COUNT(DISTINCT media_id) as contents, COUNT(*) as sessions, COALESCE(SUM(watched_seconds), 0) as seconds, MAX(created_at) as last_seen')
            ->groupBy('user_id')
            ->orderByDesc('last_seen')
            ->with('user:id,name,country_code', 'user.country:code,name,flag_emoji')
            ->paginate(25)
            ->withQueryString();

        return view('audience.index', [
            'stats' => $stats,
            'topContents' => $topContents,
            'viewers' => $viewers,
            'contents' => $contents,
            'periods' => self::PERIODS,
            'period' => $period,
            'mediaId' => $mediaId,
        ]);
    }
}
