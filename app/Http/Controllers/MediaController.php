<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Media;
use App\Services\BunnyStreamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Catalogue Films / Séries.
 *
 * Le contenu vidéo proprement dit n'est plus uploadé ici : il est hébergé chez
 * Bunny Stream et le Media stocke uniquement son video_id (GUID Bunny).
 *
 * - Pour un film    → on choisit la vidéo Bunny au moment de la création.
 * - Pour une série  → on ne choisit pas de vidéo sur le Media lui-même ; les
 *                     vidéos sont attribuées aux Episodes (cf. EpisodeController).
 */
class MediaController extends Controller
{
    public function __construct(protected BunnyStreamService $bunny)
    {
    }

    public function index()
    {
        $media = Media::with('category')
            ->visibleTo(auth()->user())
            ->latest()
            ->paginate(12);

        return view('media.index', compact('media'));
    }

    public function create(Request $request)
    {
        $categories = Category::orderBy('name')->get();
        // Si on vient de la library Bunny ("Créer un film à partir de cette vidéo")
        $preselectedBunnyGuid = $request->query('bunny');

        // Type forcé selon la section d'origine (menu Films / menu Séries).
        // Si présent, on masque le sélecteur Film/Série dans le formulaire.
        $forcedType = in_array($request->query('type'), ['movie', 'series'], true)
            ? $request->query('type')
            : null;

        return view('media.create', compact('categories', 'preselectedBunnyGuid', 'forcedType'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type'         => 'required|in:movie,series',
            'category_id'  => 'required|exists:categories,id',
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'duration'     => 'nullable|integer|min:1', // minutes (films seulement)
            'release_year' => 'nullable|integer|min:1900|max:'.(date('Y') + 5),
            'seasons'      => 'nullable|integer|min:1',

            // ─ Référence Bunny Stream (films uniquement, optionnelle pour séries) ─
            'bunny_video_id' => 'nullable|string|max:128',

            // ─ Visuels uploadés en local (encore en bas débit / petits) ─
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
            'cover'     => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
            'banner'    => 'nullable|image|mimes:jpeg,png,jpg,webp|max:8192',

            'is_featured'  => 'nullable|boolean',
            'published_at' => 'nullable|date',
            'tier'         => 'nullable|in:classique,standard,premium',
        ]);

        // Pour un film, on EXIGE un video_id Bunny (sinon il n'y a rien à lire)
        if ($validated['type'] === 'movie' && empty($validated['bunny_video_id'])) {
            return back()
                ->withInput()
                ->withErrors(['bunny_video_id' => 'Choisis une vidéo Bunny pour ce film.']);
        }

        // Vérifier que cette vidéo Bunny n'est pas déjà attribuée à un autre Media/Episode
        if (! empty($validated['bunny_video_id'])
            && $this->isBunnyVideoTaken($validated['bunny_video_id'])) {
            return back()->withInput()->withErrors([
                'bunny_video_id' => 'Cette vidéo Bunny est déjà attribuée à un autre film ou épisode.',
            ]);
        }

        $data = $this->mediaPayload($request, $validated);
        $data['user_id'] = auth()->id(); // propriétaire = créateur (producteur ou admin)
        $data['tier'] = $validated['tier'] ?? 'classique';
        // Un contenu uploadé par un PRODUCTEUR passe en modération (l'assistant/
        // l'admin le valide et confirme catégorie + tier). Un admin publie direct.
        $data['moderation_status'] = auth()->user()->isProducer() ? 'pending' : 'approved';

        // Visuels
        foreach (['thumbnail', 'cover', 'banner'] as $imgField) {
            if ($request->hasFile($imgField)) {
                $folder = $imgField === 'thumbnail' ? 'thumbnails' : ($imgField === 'cover' ? 'covers' : 'banners');
                $data[$imgField.'_path'] = $request->file($imgField)->store($folder, 'public');
            }
        }

        Media::create($data);

        return redirect()->route('media.index')
            ->with('success', 'Média créé avec succès.');
    }

    public function show(Media $medium)
    {
        $this->authorizeOwnership($medium);
        $medium->load('category');

        $seasons = $medium->isSeries()
            ? $medium->seasonsRelation()->with(['episodes' => fn ($q) => $q->orderBy('episode_number')])->get()
            : collect();

        return view('media.show', compact('medium', 'seasons'));
    }

    public function edit(Media $medium)
    {
        $this->authorizeOwnership($medium);
        $categories = Category::orderBy('name')->get();

        $seasons = $medium->isSeries()
            ? $medium->seasonsRelation()->with(['episodes' => fn ($q) => $q->orderBy('episode_number')])->get()
            : collect();

        return view('media.edit', compact('medium', 'categories', 'seasons'));
    }

    public function update(Request $request, Media $medium)
    {
        $this->authorizeOwnership($medium);

        $validated = $request->validate([
            'type'         => 'required|in:movie,series',
            'category_id'  => 'required|exists:categories,id',
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'duration'     => 'nullable|integer|min:1',
            'release_year' => 'nullable|integer|min:1900|max:'.(date('Y') + 5),
            'seasons'      => 'nullable|integer|min:1',
            'bunny_video_id' => 'nullable|string|max:128',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
            'cover'     => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
            'banner'    => 'nullable|image|mimes:jpeg,png,jpg,webp|max:8192',
            'is_featured'  => 'nullable|boolean',
            'published_at' => 'nullable|date',
            'tier'         => 'nullable|in:classique,standard,premium',
        ]);

        if ($validated['type'] === 'movie' && empty($validated['bunny_video_id'])) {
            return back()->withInput()->withErrors([
                'bunny_video_id' => 'Choisis une vidéo Bunny pour ce film.',
            ]);
        }

        // Vérifier disponibilité de la vidéo Bunny (si on change)
        $newGuid = $validated['bunny_video_id'] ?? null;
        if ($newGuid && $newGuid !== $medium->video_id && $this->isBunnyVideoTaken($newGuid)) {
            return back()->withInput()->withErrors([
                'bunny_video_id' => 'Cette vidéo Bunny est déjà attribuée ailleurs.',
            ]);
        }

        $data = $this->mediaPayload($request, $validated, $medium);

        foreach (['thumbnail', 'cover', 'banner'] as $imgField) {
            if ($request->hasFile($imgField)) {
                $folder = $imgField === 'thumbnail' ? 'thumbnails' : ($imgField === 'cover' ? 'covers' : 'banners');
                $old = $medium->{$imgField.'_path'};
                if ($old && ! str_starts_with($old, 'http')) {
                    Storage::disk('public')->delete($old);
                }
                $data[$imgField.'_path'] = $request->file($imgField)->store($folder, 'public');
            }
        }

        $medium->update($data);

        return redirect()->route('media.index')
            ->with('success', 'Média mis à jour avec succès.');
    }

    public function destroy(Media $medium)
    {
        $this->authorizeOwnership($medium);

        foreach (['thumbnail_path', 'cover_path', 'banner_path'] as $col) {
            if ($medium->$col && ! str_starts_with($medium->$col, 'http')) {
                Storage::disk('public')->delete($medium->$col);
            }
        }
        // La vidéo elle-même reste chez Bunny — on ne la supprime PAS automatiquement
        $medium->delete();

        return redirect()->route('media.index')
            ->with('success', 'Média supprimé. (La vidéo reste disponible côté Bunny.)');
    }

    /* ---------------------------------------------------------------
     |  Helpers
     * --------------------------------------------------------------- */

    /**
     * Un producteur ne peut agir que sur SES contenus. L'admin peut tout.
     */
    protected function authorizeOwnership(Media $medium): void
    {
        $user = auth()->user();
        if ($user && $user->isProducer() && $medium->user_id !== $user->id) {
            abort(403, "Ce contenu ne vous appartient pas.");
        }
    }

    /**
     * Génère un slug unique à partir du titre (suffixe -2, -3… si déjà pris).
     */
    protected function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'media';
        $slug = $base;
        $i = 2;
        while (
            Media::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /**
     * Construit le payload à enregistrer (transformation des champs Bunny + slug + duration).
     */
    protected function mediaPayload(Request $request, array $validated, ?Media $ignore = null): array
    {
        $data = $validated;

        $data['slug']        = $this->uniqueSlug($validated['title'], $ignore?->id);
        $data['is_featured'] = (bool) $request->boolean('is_featured');

        // Conversion durée (minutes → secondes) — pour les films seulement.
        // Pour une série, la durée a moins de sens : on la laisse à 0 et on
        // additionnera les épisodes au besoin.
        if (! empty($data['duration'])) {
            $data['duration'] = (int) $data['duration'] * 60;
        }

        // Référence vidéo : Bunny, ou fallback LOCAL (guid "local:{id}") pour tester sans Bunny.
        $sel = $validated['bunny_video_id'] ?? null;

        if ($sel && str_starts_with($sel, 'local:')) {
            // Vidéo locale publiée depuis la page Upload (video_provider = 'local').
            $upload = \App\Models\BunnyUpload::find((int) substr($sel, 6));
            $data['video_provider']   = 'local';
            $data['video_path']       = $upload?->local_path;
            $data['video_id']         = null;
            $data['video_library_id'] = null;
            $data['video_metadata']   = null;
        } elseif (! empty($sel)) {
            $data['video_provider']   = 'bunny';
            $data['video_id']         = $sel;
            $data['video_path']       = null;
            $data['video_library_id'] = (string) config('services.bunny.library_id');

            // Essayer de récupérer la durée et le titre Bunny si pas fournis
            try {
                if ($this->bunny->isConfigured()) {
                    $bv = $this->bunny->getVideo($sel);
                    $data['video_metadata'] = $bv;
                    if (empty($data['duration']) && ! empty($bv['length'])) {
                        $data['duration'] = (int) $bv['length']; // déjà en secondes
                    }
                }
            } catch (\Throwable $e) {
                // pas bloquant
            }
        } else {
            // Série sans vidéo directe (les épisodes auront chacun leur video_id)
            $data['video_provider']   = null;
            $data['video_id']         = null;
            $data['video_path']       = null;
            $data['video_library_id'] = null;
        }

        unset($data['bunny_video_id']);

        return $data;
    }

    /**
     * Une vidéo Bunny est "prise" si elle est déjà référencée par un Media ou un Episode.
     */
    protected function isBunnyVideoTaken(string $guid, ?int $ignoreMediaId = null): bool
    {
        $mediaQuery = Media::where('video_provider', 'bunny')->where('video_id', $guid);
        if ($ignoreMediaId) {
            $mediaQuery->where('id', '!=', $ignoreMediaId);
        }
        if ($mediaQuery->exists()) {
            return true;
        }

        return \App\Models\Episode::where('video_provider', 'bunny')->where('video_id', $guid)->exists();
    }
}
