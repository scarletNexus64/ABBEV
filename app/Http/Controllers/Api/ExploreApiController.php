<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\RubriqueResource;
use App\Models\Agent;
use App\Models\AwardEdition;
use App\Models\CastingCall;
use App\Models\Category;
use App\Models\Course;
use App\Models\Media;
use App\Models\ProjectCall;
use App\Models\Rubrique;
use App\Models\Screening;
use App\Models\Talent;
use App\Support\MediaFormat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Sommaire de l'écran « Explorer » de l'app : un seul appel qui renvoie, pour
 * chaque section de cat.md, de quoi afficher sa carte (compteurs par
 * sous-catégorie, état du vote, annonces ouvertes…).
 *
 * La STRUCTURE de l'écran (sections, icônes, libellés) vit dans l'app, qui
 * l'affiche même hors ligne ; ce point d'API n'apporte que les chiffres.
 * Route publique : un visiteur non connecté explore aussi.
 */
class ExploreApiController extends Controller
{
    /** Sélections éditoriales exposées comme sections à part entière. */
    private const RUBRIQUES = ['avant-premiere', 'sport', 'jeux'];

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');

        return response()->json(['data' => [
            'featured' => [
                'featured_count' => Media::published()->where('is_featured', true)->count(),
                'new_count' => Media::published()->where('published_at', '>=', now()->subDays(30))->count(),
            ],
            'movies' => $this->formatCounts('movie'),
            'series' => $this->formatCounts('series'),
            'genres' => CategoryResource::collection(
                Category::genres()
                    ->withCount(['media' => fn ($q) => $q->published()])
                    ->get()
            )->resolve($request),
            'rubriques' => $this->rubriques($request, $user),
            'awards' => $this->awards(),
            'talents' => [
                'actors' => Talent::published()->where('kind', 'acteur')->count(),
                'technicians' => Talent::published()->where('kind', 'technicien')->count(),
                'agents' => Agent::published()->count(),
                'open_castings' => CastingCall::acceptingApplications()->count(),
            ],
            'courses' => [
                'video' => Course::published()->where('type', 'video')->count(),
                'document' => Course::published()->where('type', 'document')->count(),
            ],
            'calls' => $this->openCalls(),
            'tickets' => [
                'screenings' => $this->ticketOffers($user, 'seance'),
                'codes' => $this->ticketOffers($user, 'code'),
            ],
        ]]);
    }

    /** @return array{total: int, formats: array<string, int>} */
    private function formatCounts(string $type): array
    {
        $counts = Media::published()
            ->where('type', $type)
            ->whereNotNull('format')
            ->select('format', DB::raw('count(*) as aggregate'))
            ->groupBy('format')
            ->pluck('aggregate', 'format');

        $formats = [];
        foreach (MediaFormat::optionsFor($type) as $format) {
            $formats[$format] = (int) ($counts[$format] ?? 0);
        }

        return [
            'total' => Media::published()->where('type', $type)->count(),
            'formats' => $formats,
        ];
    }

    /**
     * Avant-première, Sport, Jeux : la rubrique (pour l'ouvrir) et ses
     * compteurs. Une rubrique inactive ou réservée à un forfait supérieur
     * part avec `locked` plutôt que de disparaître : l'app peut alors
     * inviter à s'abonner au lieu de masquer la section.
     */
    private function rubriques(Request $request, $user): array
    {
        $out = [];

        $rubriques = Rubrique::whereIn('slug', self::RUBRIQUES)->where('is_active', true)->get();
        foreach ($rubriques as $rubrique) {
            // `reorder()` : la relation trie sur le pivot, ce que PostgreSQL
            // refuse dans une requête groupée.
            $counts = $rubrique->media()->published()->reorder()
                ->select('media.type', DB::raw('count(*) as aggregate'))
                ->groupBy('media.type')
                ->pluck('aggregate', 'type');

            $out[$rubrique->slug] = (new RubriqueResource($rubrique))->resolve($request) + [
                'movies_count' => (int) ($counts['movie'] ?? 0),
                'series_count' => (int) ($counts['series'] ?? 0),
                'required_tier' => $rubrique->required_tier,
                'locked' => ! $rubrique->isAccessibleBy($user),
            ];
        }

        return $out;
    }

    private function awards(): ?array
    {
        $edition = AwardEdition::where('is_current', true)->withCount('categories')->first();
        if (! $edition) {
            return null;
        }

        return [
            'id' => (int) $edition->id,
            'name' => $edition->name,
            'year' => (int) $edition->year,
            'status' => $edition->status(),
            'voting_starts_at' => $edition->voting_starts_at?->toIso8601String(),
            'voting_ends_at' => $edition->voting_ends_at?->toIso8601String(),
            'categories_count' => (int) $edition->categories_count,
        ];
    }

    /** Appels OUVERTS par famille et sous-catégorie. */
    private function openCalls(): array
    {
        $rows = ProjectCall::where('status', 'open')
            ->where(fn ($q) => $q->whereNull('closes_at')->orWhere('closes_at', '>', now()))
            ->where(fn ($q) => $q->whereNull('opens_at')->orWhere('opens_at', '<=', now()))
            ->select('type', 'target', DB::raw('count(*) as aggregate'))
            ->groupBy('type', 'target')
            ->get();

        $out = [];
        foreach (ProjectCall::TARGETS as $type => $targets) {
            $byTarget = [];
            foreach (array_keys($targets) as $target) {
                $byTarget[$target] = (int) ($rows->first(
                    fn ($r) => $r->type === $type && $r->target === $target
                )?->aggregate ?? 0);
            }
            $out[$type] = ['open' => array_sum($byTarget), 'targets' => $byTarget];
        }

        return $out;
    }

    /** Offres de billetterie en vente dans le pays de l'utilisateur. */
    private function ticketOffers($user, string $kind): int
    {
        return Screening::onSale()
            ->where('kind', $kind)
            ->when($user?->country_code, fn ($q, $country) => $q->where(
                fn ($c) => $c->whereNull('country_code')->orWhere('country_code', $country)
            ))
            ->count();
    }
}
