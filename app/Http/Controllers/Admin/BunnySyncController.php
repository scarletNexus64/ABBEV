<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BunnyUpload;
use App\Models\Episode;
use App\Models\Media;
use App\Services\BunnyStreamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Vue admin sur la library Bunny.
 *
 * → /admin/bunny/library
 *     Liste TOUTES les vidéos Bunny avec leur statut côté Laravel :
 *     - libre (aucun Media ni Episode ne l'utilise)
 *     - utilisée par un Media (film) → lien "Éditer le film"
 *     - utilisée par un Episode (série/saison) → lien "Éditer l'épisode"
 *
 * → GET /admin/bunny/videos/available?q=...
 *     Endpoint JSON pour le picker des formulaires de création
 *     de Media et d'Épisode. Retourne SEULEMENT les vidéos non utilisées,
 *     filtrées par recherche optionnelle.
 *
 * On ne crée plus rien automatiquement : c'est l'admin qui choisit
 * une vidéo Bunny en remplissant un film ou un épisode.
 */
class BunnySyncController extends Controller
{
    public function __construct(protected BunnyStreamService $bunny)
    {
    }

    public function library(Request $request): View|RedirectResponse
    {
        if (! $this->bunny->isConfigured()) {
            return redirect()->route('admin.dashboard')->with('error',
                'Bunny Stream non configuré. Renseignez BUNNY_STREAM_LIBRARY_ID, BUNNY_STREAM_API_KEY '
                .'et BUNNY_STREAM_CDN_HOSTNAME dans le .env.');
        }

        try {
            $videos = $this->fetchVideosCached();
        } catch (\Throwable $e) {
            Log::error('Bunny listAllVideos failed', ['exception' => $e]);
            return redirect()->route('admin.dashboard')
                ->with('error', 'Erreur API Bunny : '.$e->getMessage());
        }

        $usageMap = $this->buildUsageMap();

        $rows = collect($videos)->map(function ($v) use ($usageMap) {
            $guid  = $v['guid'] ?? null;
            $usage = $usageMap[$guid] ?? null;

            return [
                'guid'    => $guid,
                'title'   => $v['title'] ?? 'Sans titre',
                'length'  => (int) ($v['length'] ?? 0),
                'status'  => (int) ($v['status'] ?? 0), // 4 = ready
                'storage' => (int) ($v['storageSize'] ?? 0),
                'thumb'   => $guid ? $this->bunny->thumbnailUrl($guid) : null,
                'usage'   => $usage,           // null | ['type' => 'movie'|'episode', 'media' => Media, 'episode' => Episode]
            ];
        });

        return view('admin.bunny.library', [
            'videos'    => $rows,
            'total'     => $rows->count(),
            'usedCount' => $rows->filter(fn ($r) => $r['usage'] !== null)->count(),
            'freeCount' => $rows->filter(fn ($r) => $r['usage'] === null)->count(),
        ]);
    }

    /**
     * Endpoint JSON pour le picker des formulaires.
     * Retourne UNIQUEMENT les vidéos non encore attribuées.
     */
    public function available(Request $request): JsonResponse
    {
        $q           = trim((string) $request->get('q', ''));
        $includeGuid = $request->get('include'); // pour pré-cocher la vidéo actuelle en édition
        $usageMap    = $this->buildUsageMap();
        $error       = null;

        // Cloisonnement producteur : il ne voit QUE ses propres vidéos.
        $user     = $request->user();
        $producer = $user && $user->isProducer();
        $ownGuids = $producer
            ? BunnyUpload::where('user_id', $user->workspaceId())->whereNotNull('bunny_guid')->pluck('bunny_guid')->all()
            : null; // null = aucune restriction (admin)

        // 1. Vidéos Bunny (best-effort : si Bunny échoue, on garde les vidéos locales).
        //    Si Bunny a échoué récemment, on ne re-tente pas tout de suite : ça évite
        //    que le picker « tourne » 30 s à chaque ouverture (timeout réseau Bunny).
        $bunnyItems = collect();
        if ($this->bunny->isConfigured() && ! Cache::get('bunny.unreachable')) {
            try {
                $bunnyItems = collect($this->fetchVideosCached())
                    ->filter(function ($v) use ($usageMap, $includeGuid, $ownGuids) {
                        $guid = $v['guid'] ?? null;
                        if (! $guid) {
                            return false;
                        }
                        if ($includeGuid && $guid === $includeGuid) {
                            return true;
                        }
                        // Producteur : seulement les vidéos Bunny lui appartenant.
                        if ($ownGuids !== null && ! in_array($guid, $ownGuids, true)) {
                            return false;
                        }
                        return ! isset($usageMap[$guid]);
                    })
                    ->filter(function ($v) use ($q) {
                        if ($q === '') return true;
                        return stripos($v['title'] ?? '', $q) !== false
                            || stripos($v['guid'] ?? '', $q) !== false;
                    })
                    ->values()
                    ->map(fn ($v) => [
                        'guid'   => $v['guid'],
                        'title'  => $v['title'] ?? 'Sans titre',
                        'length' => (int) ($v['length'] ?? 0),
                        'thumb'  => $this->bunny->thumbnailUrl($v['guid']),
                        'size'   => (int) ($v['storageSize'] ?? 0),
                        'status' => (int) ($v['status'] ?? 0),
                        'source' => 'bunny',
                    ]);
            } catch (\Throwable $e) {
                // Bunny injoignable : on note l'indisponibilité 2 min pour ne pas
                // re-bloquer le picker à chaque ouverture.
                Cache::put('bunny.unreachable', true, 120);
                $error = 'Bunny indisponible : '.$e->getMessage();
            }
        } elseif (Cache::get('bunny.unreachable')) {
            $error = 'Bunny momentanément injoignable — vidéos locales affichées.';
        } else {
            $error = 'Bunny non configuré.';
        }

        // 2. Vidéos locales publiées (non encore attribuées), cloisonnées au producteur.
        $localItems = $this->availableLocalVideos($usageMap, $q, $includeGuid, $producer ? $user->workspaceId() : null);

        $items = $localItems->concat($bunnyItems)->values();

        return response()->json(['data' => $items, 'count' => $items->count(), 'error' => $error]);
    }

    /**
     * Permet de purger le cache local de la liste Bunny (bouton "Rafraîchir").
     */
    public function refresh(): RedirectResponse
    {
        Cache::forget('bunny.videos.all');

        return back()->with('success', 'Cache Bunny vidé. La prochaine requête ira directement chez Bunny.');
    }

    /* ---------------------------------------------------------------
     |  Helpers
     * --------------------------------------------------------------- */

    /**
     * Récupère TOUTES les vidéos Bunny (cache 60 s pour éviter de marteler l'API).
     */
    protected function fetchVideosCached(int $ttl = 60): array
    {
        return Cache::remember('bunny.videos.all', $ttl, fn () => $this->bunny->listAllVideos());
    }

    /**
     * Vidéos locales publiées (fallback de test sans Bunny), non encore attribuées.
     * Représentées par un guid spécial "local:{id}" interprété par MediaController/EpisodeController.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function availableLocalVideos(array $usageMap, string $q, ?string $includeGuid, ?int $ownerId = null): \Illuminate\Support\Collection
    {
        // Chemins locaux déjà attribués à un film ou un épisode.
        $takenPaths = Media::withoutGlobalScope('workspace')->where('video_provider', 'local')->whereNotNull('video_path')->pluck('video_path')
            ->concat(Episode::query()->where('video_provider', 'local')->whereNotNull('video_path')->pluck('video_path'))
            ->filter()->unique()->all();

        return BunnyUpload::query()
            ->whereNotNull('local_path')
            ->when($ownerId, fn ($query) => $query->where('user_id', $ownerId)) // cloisonnement producteur
            ->latest()
            ->get()
            ->filter(fn (BunnyUpload $u) => $u->hasLocalCopy())
            ->filter(function (BunnyUpload $u) use ($takenPaths, $includeGuid) {
                $guid = 'local:'.$u->id;
                if ($includeGuid && $guid === $includeGuid) {
                    return true; // toujours inclure la vidéo en cours d'édition
                }
                return ! in_array($u->local_path, $takenPaths, true);
            })
            ->filter(function (BunnyUpload $u) use ($q) {
                if ($q === '') return true;
                return stripos($u->title, $q) !== false || stripos($u->original_filename, $q) !== false;
            })
            ->values()
            ->map(fn (BunnyUpload $u) => [
                'guid'   => 'local:'.$u->id,
                'title'  => $u->title.' (local)',
                'length' => 0,
                'thumb'  => null,
                'size'   => (int) $u->size_bytes,
                'status' => 4, // traité comme "prête" côté picker
                'source' => 'local',
            ]);
    }

    /**
     * Construit la map guid → ['type' => 'movie'|'episode', 'media' => Media, 'episode' => Episode]
     * pour identifier d'un seul lookup ce qui est déjà attribué.
     */
    protected function buildUsageMap(): array
    {
        $map = [];

        Media::withoutGlobalScope('workspace')
            ->where('video_provider', 'bunny')
            ->whereNotNull('video_id')
            ->get(['id', 'title', 'slug', 'type', 'video_id'])
            ->each(function (Media $m) use (&$map) {
                $map[$m->video_id] = ['type' => 'movie', 'media' => $m, 'episode' => null];
            });

        Episode::query()
            ->where('video_provider', 'bunny')
            ->whereNotNull('video_id')
            ->with('season.media')
            ->get()
            ->each(function (Episode $e) use (&$map) {
                $map[$e->video_id] = ['type' => 'episode', 'media' => $e->season?->media, 'episode' => $e];
            });

        return $map;
    }
}
