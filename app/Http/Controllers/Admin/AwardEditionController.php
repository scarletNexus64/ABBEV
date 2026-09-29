<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesUploads;
use App\Http\Controllers\Admin\Concerns\SavesTranslations;
use App\Http\Controllers\Controller;
use App\Models\AwardEdition;
use App\Models\AwardVote;
use App\Models\Media;
use App\Models\Talent;
use App\Services\AwardVotingService;
use App\Support\AwardCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Lions Head Awards : éditions, période de vote, palmarès.
 *
 * Cycle d'une édition : création (avec la grille des 29 prix de cat.md) →
 * nominations → vote public (dates) → publication des résultats. Le statut
 * se déduit des dates, sans tâche planifiée.
 */
class AwardEditionController extends Controller
{
    use HandlesUploads, SavesTranslations;

    private const TRANSLATABLE = ['tagline', 'description'];

    public function __construct(private AwardVotingService $voting)
    {
    }

    public function index()
    {
        $editions = AwardEdition::withCount(['categories', 'nominees', 'votes'])
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->get();

        return view('awards.index', ['editions' => $editions]);
    }

    public function create()
    {
        $year = (int) now()->year;

        return view('awards.create', ['edition' => new AwardEdition([
            'name' => "Lions Head Awards {$year}",
            'year' => $year,
        ])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $edition = DB::transaction(function () use ($request, $data) {
            $edition = AwardEdition::create($this->attributes($request, $data, new AwardEdition()));
            $this->saveEnglish($edition, $request, self::TRANSLATABLE);

            if ($request->boolean('apply_template', true)) {
                AwardCatalog::applyTo($edition);
            }
            // L'édition affichée dans l'app est unique sur la plateforme : seul
            // l'admin la choisit (un producteur ne remplace pas celle d'un autre).
            if ($request->user()->isAdmin()
                && ($request->boolean('is_current') || ! AwardEdition::where('is_current', true)->exists())) {
                $edition->makeCurrent();
            }

            return $edition;
        });

        return redirect()->route('awards.show', $edition)
            ->with('success', 'Édition créée. Ajoutez maintenant les nommés de chaque prix.');
    }

    /** Tableau de bord d'une édition : prix, nommés, résultats en direct. */
    public function show(Request $request, AwardEdition $edition)
    {
        $edition->load(['categories.translations', 'categories.nominees.media', 'categories.nominees.talent']);

        $votes = AwardVote::whereIn('award_category_id', $edition->categories->pluck('id'));

        return view('awards.show', [
            'edition' => $edition,
            'tab' => $request->query('tab', 'prix'),
            'stats' => [
                'votes' => (clone $votes)->count(),
                'voters' => (clone $votes)->distinct('user_id')->count('user_id'),
                'nominees' => $edition->categories->sum(fn ($c) => $c->nominees->count()),
                'empty' => $edition->categories->filter(fn ($c) => $c->nominees->isEmpty())->count(),
            ],
            'standings' => $edition->categories->mapWithKeys(fn ($c) => [$c->id => $this->voting->standings($c)]),
            'talents' => Talent::orderByTier()->get(['id', 'first_name', 'last_name', 'stage_name', 'tier', 'profession', 'kind', 'gender']),
            'catalog' => Media::approved()->orderBy('title')->get(['id', 'title', 'type', 'release_year']),
        ]);
    }

    public function edit(AwardEdition $edition)
    {
        $edition->load('translations');

        return view('awards.edit', ['edition' => $edition]);
    }

    public function update(Request $request, AwardEdition $edition)
    {
        $data = $this->validated($request, $edition);

        $edition->update($this->attributes($request, $data, $edition));
        $this->saveEnglish($edition, $request, self::TRANSLATABLE);
        if ($request->user()->isAdmin() && $request->boolean('is_current')) {
            $edition->makeCurrent();
        }

        return redirect()->route('awards.show', $edition)->with('success', 'Édition enregistrée.');
    }

    public function destroy(AwardEdition $edition)
    {
        $name = $edition->name;
        $this->forgetPublic($edition->cover_path);
        $edition->translations()->delete();
        $edition->delete();

        return redirect()->route('awards.index')->with('success', "« {$name} » supprimée, avec ses prix, nommés et votes.");
    }

    /** Publie le palmarès (lauréats = plus votés, sauf choix du jury). */
    public function publish(AwardEdition $edition)
    {
        $this->voting->publishResults($edition);

        return back()->with('success', 'Palmarès publié : les résultats sont visibles dans l\'application.');
    }

    public function unpublish(AwardEdition $edition)
    {
        $this->voting->unpublishResults($edition);

        return back()->with('success', 'Résultats retirés de l\'application.');
    }

    public function makeCurrent(AwardEdition $edition)
    {
        $edition->makeCurrent();

        return back()->with('success', "« {$edition->name} » est désormais l'édition affichée dans l'application.");
    }

    /** Ajoute les prix de la grille cat.md absents de l'édition. */
    public function applyTemplate(AwardEdition $edition)
    {
        $created = AwardCatalog::applyTo($edition);

        return back()->with('success', $created
            ? "{$created} prix ajouté(s) depuis la grille officielle."
            : 'Tous les prix de la grille sont déjà présents.');
    }

    private function validated(Request $request, ?AwardEdition $edition = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:120',
            'year' => 'required|integer|min:2000|max:2100',
            'tagline' => 'nullable|string|max:190',
            'description' => 'nullable|string|max:4000',
            'voting_starts_at' => 'nullable|date',
            'voting_ends_at' => 'nullable|date|after:voting_starts_at',
            'ceremony_at' => 'nullable|date',
            'ceremony_venue' => 'nullable|string|max:190',
            'cover' => 'nullable|image|max:4096',
        ] + $this->translationRules(self::TRANSLATABLE));
    }

    private function attributes(Request $request, array $data, AwardEdition $edition): array
    {
        return [
            'name' => $data['name'],
            'year' => $data['year'],
            'tagline' => $data['tagline'] ?? null,
            'description' => $data['description'] ?? null,
            'voting_starts_at' => $data['voting_starts_at'] ?? null,
            'voting_ends_at' => $data['voting_ends_at'] ?? null,
            'ceremony_at' => $data['ceremony_at'] ?? null,
            'ceremony_venue' => $data['ceremony_venue'] ?? null,
            'cover_path' => $this->replaceImage($request, 'cover', 'awards', $edition->cover_path),
        ];
    }
}
