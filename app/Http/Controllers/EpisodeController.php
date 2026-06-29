<?php

namespace App\Http\Controllers;

use App\Models\Episode;
use App\Models\Media;
use App\Models\Season;
use App\Services\BunnyStreamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Gestion des saisons et épisodes d'une série.
 *
 * Comme pour les Media (films), la vidéo d'un épisode n'est pas uploadée :
 * elle est référencée depuis Bunny Stream via bunny_video_id.
 */
class EpisodeController extends Controller
{
    public function __construct(protected BunnyStreamService $bunny)
    {
    }

    public function index(Media $media)
    {
        if (! $media->isSeries()) {
            return redirect()->route('media.index')->with('error', "Ce média n'est pas une série.");
        }

        $seasons = $media->seasonsRelation()->with('episodes')->get();

        return view('episodes.index', compact('media', 'seasons'));
    }

    public function createSeason(Request $request, Media $media)
    {
        $validated = $request->validate([
            'season_number' => 'required|integer|min:1',
            'title'         => 'nullable|string|max:255',
            'description'   => 'nullable|string',
            'release_year'  => 'nullable|integer|min:1900|max:'.(date('Y') + 5),
        ]);

        $season = $media->seasonsRelation()->create($validated);

        return redirect()->route('episodes.index', $media)
            ->with('success', "Saison {$season->season_number} créée.");
    }

    public function create(Season $season)
    {
        return view('episodes.create', compact('season'));
    }

    public function store(Request $request, Season $season)
    {
        $validated = $request->validate([
            'episode_number' => 'required|integer|min:1',
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'duration'       => 'nullable|integer|min:1', // minutes
            'bunny_video_id' => 'required|string|max:128',
            'thumbnail'      => 'nullable|image|max:10240',
            'published_at'   => 'nullable|date',
        ]);

        // Vérifier que la vidéo Bunny n'est pas déjà prise
        if ($this->isBunnyVideoTaken($validated['bunny_video_id'])) {
            return back()->withInput()->withErrors([
                'bunny_video_id' => 'Cette vidéo Bunny est déjà attribuée à un autre film ou épisode.',
            ]);
        }

        $payload = $this->buildPayload($validated, $request);

        $episode = $season->episodes()->create($payload);
        $season->updateEpisodesCount();

        return redirect()->route('episodes.index', $season->media)
            ->with('success', "Épisode {$episode->episode_number} ajouté.");
    }

    public function edit(Episode $episode)
    {
        $season = $episode->season;

        return view('episodes.edit', compact('episode', 'season'));
    }

    public function update(Request $request, Episode $episode)
    {
        $validated = $request->validate([
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'duration'       => 'nullable|integer|min:1',
            'bunny_video_id' => 'required|string|max:128',
            'thumbnail'      => 'nullable|image|max:10240',
            'published_at'   => 'nullable|date',
        ]);

        // Vérifier disponibilité si la vidéo a changé
        if ($validated['bunny_video_id'] !== $episode->video_id
            && $this->isBunnyVideoTaken($validated['bunny_video_id'])) {
            return back()->withInput()->withErrors([
                'bunny_video_id' => 'Cette vidéo Bunny est déjà attribuée ailleurs.',
            ]);
        }

        $payload = $this->buildPayload($validated, $request);

        if ($request->hasFile('thumbnail') && $episode->thumbnail_path) {
            Storage::disk('public')->delete($episode->thumbnail_path);
        }

        $episode->update($payload);

        return redirect()->route('episodes.index', $episode->season->media)
            ->with('success', "Épisode {$episode->episode_number} mis à jour.");
    }

    public function destroy(Episode $episode)
    {
        $season = $episode->season;
        $media  = $season->media;

        if ($episode->thumbnail_path) {
            Storage::disk('public')->delete($episode->thumbnail_path);
        }

        // La vidéo elle-même reste chez Bunny — on ne la supprime PAS
        $episode->delete();
        $season->updateEpisodesCount();

        return redirect()->route('episodes.index', $media)
            ->with('success', 'Épisode supprimé. (La vidéo reste disponible côté Bunny.)');
    }

    public function destroySeason(Season $season)
    {
        $media = $season->media;
        $num   = $season->season_number;

        foreach ($season->episodes as $ep) {
            if ($ep->thumbnail_path) {
                Storage::disk('public')->delete($ep->thumbnail_path);
            }
        }

        $season->delete();

        return redirect()->route('episodes.index', $media)
            ->with('success', "Saison {$num} supprimée.");
    }

    /* ---------------------------------------------------------------
     |  Helpers
     * --------------------------------------------------------------- */
    protected function buildPayload(array $validated, Request $request): array
    {
        $data = $validated;

        $sel = $validated['bunny_video_id'];

        if (str_starts_with($sel, 'local:')) {
            // Vidéo locale (fallback de test sans Bunny).
            $upload = \App\Models\BunnyUpload::find((int) substr($sel, 6));
            $data['video_provider']   = 'local';
            $data['video_path']       = $upload?->local_path;
            $data['video_id']         = null;
            $data['video_library_id'] = null;
            $data['video_metadata']   = null;
        } else {
            $data['video_provider']   = 'bunny';
            $data['video_id']         = $sel;
            $data['video_library_id'] = (string) config('services.bunny.library_id');
        }

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail_path'] = $request->file('thumbnail')->store('thumbnails', 'public');
        }

        // Conversion durée min → secondes (fallback si Bunny indisponible)
        if (! empty($data['duration'])) {
            $data['duration'] = (int) $data['duration'] * 60;
        }

        // La durée vient de Bunny : c'est la source de vérité (vraie durée du
        // fichier encodé). On l'applique systématiquement quand on l'obtient.
        if (($data['video_provider'] ?? null) === 'bunny') {
            try {
                if ($this->bunny->isConfigured()) {
                    $bv = $this->bunny->getVideo($sel);
                    $data['video_metadata'] = $bv;
                    if (! empty($bv['length'])) {
                        $data['duration'] = (int) $bv['length'];
                    }
                }
            } catch (\Throwable $e) {
                // pas bloquant — on garde la durée saisie si présente
            }
        }

        unset($data['bunny_video_id']);

        return $data;
    }

    protected function isBunnyVideoTaken(string $guid): bool
    {
        $inMedia = Media::where('video_provider', 'bunny')->where('video_id', $guid)->exists();
        if ($inMedia) {
            return true;
        }

        return Episode::where('video_provider', 'bunny')->where('video_id', $guid)->exists();
    }
}
