<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesUploads;
use App\Http\Controllers\Admin\Concerns\SavesTranslations;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Rubrique;
use App\Support\TierAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Sélections éditoriales de cat.md : « À la une », « Avant-première »
 * (films, séries), « Sport » et « Jeux ».
 *
 *  - À la une = les contenus marqués `is_featured` (bandeau d'accueil et
 *    onglet Nouveautés de l'app) ;
 *  - les autres sont des rubriques de type `media` dont l'équipe choisit
 *    et ordonne le contenu, avec un forfait minimal éventuel.
 */
class RubriqueController extends Controller
{
    use HandlesUploads, SavesTranslations;

    private const TRANSLATABLE = ['name', 'description'];

    public function index()
    {
        $rubriques = Rubrique::orderBy('sort_order')->orderBy('id')
            ->withCount([
                'media as movies_count' => fn ($q) => $q->where('type', 'movie'),
                'media as series_count' => fn ($q) => $q->where('type', 'series'),
                'oeuvres',
            ])
            ->get();

        return view('rubriques.index', [
            'rubriques' => $rubriques,
            'featuredCount' => Media::where('is_featured', true)->count(),
            'featuredPreview' => Media::where('is_featured', true)->latest('published_at')->take(6)->get(),
        ]);
    }

    /** « À la une » : contenus mis en avant. */
    public function featured(Request $request)
    {
        return view('rubriques.featured', [
            'items' => Media::with('category')->where('is_featured', true)->latest('published_at')->get(),
            'results' => $this->search($request, fn ($q) => $q->where('is_featured', false)),
            'q' => (string) $request->query('q', ''),
        ]);
    }

    public function edit(Request $request, Rubrique $rubrique)
    {
        if ($rubrique->isOeuvre()) {
            return redirect()->route('oeuvres.index');
        }

        $rubrique->load('translations');
        $items = $rubrique->media()->with('category')->get();

        return view('rubriques.edit', [
            'rubrique' => $rubrique,
            'items' => $items,
            'results' => $this->search($request, fn ($q) => $q->whereNotIn('media.id', $items->pluck('id'))),
            'q' => (string) $request->query('q', ''),
            'tiers' => TierAccess::LABELS,
        ]);
    }

    public function update(Request $request, Rubrique $rubrique)
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'description' => 'nullable|string|max:500',
            'required_tier' => ['nullable', Rule::in(TierAccess::TIERS)],
            'is_active' => 'required|boolean',
            'cover' => 'nullable|image|max:4096',
        ] + $this->translationRules(self::TRANSLATABLE));

        $rubrique->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'required_tier' => ($data['required_tier'] ?? null) ?: null,
            'is_active' => (bool) $data['is_active'],
            'cover_path' => $this->replaceImage($request, 'cover', 'rubriques', $rubrique->cover_path),
        ]);
        $this->saveEnglish($rubrique, $request, self::TRANSLATABLE);

        return back()->with('success', "Sélection « {$rubrique->name} » enregistrée.");
    }

    public function attach(Request $request, Rubrique $rubrique)
    {
        $mediaId = $request->validate(['media_id' => 'required|integer|exists:media,id'])['media_id'];

        $next = (int) DB::table('media_rubrique')->where('rubrique_id', $rubrique->id)->max('sort_order') + 1;
        $rubrique->media()->syncWithoutDetaching([$mediaId => ['sort_order' => $next]]);

        return back()->with('success', 'Contenu ajouté à la sélection.');
    }

    public function detach(Rubrique $rubrique, Media $media)
    {
        $rubrique->media()->detach($media->id);

        return back()->with('success', "« {$media->title} » retiré de la sélection.");
    }

    /** Monte ou descend un contenu d'un cran (`direction` = up|down). */
    public function move(Request $request, Rubrique $rubrique, Media $media)
    {
        $up = $request->input('direction') === 'up';

        $rows = DB::table('media_rubrique')->where('rubrique_id', $rubrique->id)
            ->orderBy('sort_order')->orderBy('id')->get(['id', 'media_id', 'sort_order'])->values();
        $index = $rows->search(fn ($r) => (int) $r->media_id === (int) $media->id);
        $swap = $index === false ? null : $rows->get($up ? $index - 1 : $index + 1);

        if ($swap) {
            $current = $rows[$index];
            // Réécrit un ordre dense (1, 2, 3…) : des ex æquo hérités
            // rendraient l'échange sans effet.
            $order = $rows->pluck('id')->all();
            [$order[$index], $order[$up ? $index - 1 : $index + 1]] = [$swap->id, $current->id];
            foreach ($order as $i => $pivotId) {
                DB::table('media_rubrique')->where('id', $pivotId)->update(['sort_order' => $i + 1]);
            }
        }

        return back();
    }

    public function toggleFeatured(Media $media)
    {
        $media->update(['is_featured' => ! $media->is_featured]);

        return back()->with('success', $media->is_featured
            ? "« {$media->title} » est désormais à la une."
            : "« {$media->title} » n'est plus à la une.");
    }

    /** Recherche dans le catalogue publié (12 résultats), hors contenus exclus. */
    private function search(Request $request, callable $exclude)
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return collect();
        }

        $like = '%' . mb_strtolower($q) . '%';

        return Media::with('category')
            ->approved()
            ->whereRaw('LOWER(title) LIKE ?', [$like])
            ->tap($exclude)
            ->orderByDesc('published_at')
            ->limit(12)
            ->get();
    }
}
