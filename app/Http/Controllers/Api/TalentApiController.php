<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AgentResource;
use App\Http\Resources\TalentResource;
use App\Models\Agent;
use App\Models\Talent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Annuaire des talents (acteurs, actrices, techniciens) et des agents.
 * Consultation publique ; seules les fiches publiées sortent.
 */
class TalentApiController extends Controller
{
    /**
     * GET /talents
     *
     * Filtres : `kind` (acteur|technicien), `tier` (A2…D), `profession`,
     * `q` (nom), `featured=1`. Tri : rang puis nom.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Talent::published()->orderByTier();

        if (in_array($request->query('kind'), array_keys(Talent::KINDS), true)) {
            $query->where('kind', $request->query('kind'));
        }
        if (in_array($request->query('tier'), Talent::TIERS, true)) {
            $query->where('tier', $request->query('tier'));
        }
        if (array_key_exists((string) $request->query('profession'), Talent::PROFESSIONS)) {
            $query->where('profession', $request->query('profession'));
        }
        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }
        if ($q = trim((string) $request->query('q', ''))) {
            $like = '%' . mb_strtolower($q) . '%';
            $query->where(fn ($s) => $s
                ->whereRaw('LOWER(first_name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(stage_name) LIKE ?', [$like]));
        }

        $page = $query->paginate(min(48, max(6, (int) $request->query('per_page', 24))));

        return response()->json([
            'data' => TalentResource::collection($page->items())->resolve($request),
            'meta' => $this->meta($page),
        ]);
    }

    /** GET /talents/{talent} */
    public function show(Request $request, Talent $talent): JsonResponse
    {
        abort_unless($talent->is_published, 404);

        $talent->load([
            'agent' => fn ($q) => $q->withCount(['talents' => fn ($t) => $t->where('is_published', true)]),
            'credits.media',
        ]);

        return response()->json([
            'data' => (new TalentResource($talent))->detailed()->resolve($request),
        ]);
    }

    /** GET /agents — filtre `represents` (acteurs|techniciens) et `q`. */
    public function agents(Request $request): JsonResponse
    {
        $query = Agent::published()
            ->representing($request->query('represents'))
            ->withCount(['talents' => fn ($t) => $t->where('is_published', true)])
            ->orderBy('name');

        if ($q = trim((string) $request->query('q', ''))) {
            $like = '%' . mb_strtolower($q) . '%';
            $query->where(fn ($s) => $s
                ->whereRaw('LOWER(name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(agency) LIKE ?', [$like]));
        }

        $page = $query->paginate(min(48, max(6, (int) $request->query('per_page', 24))));

        return response()->json([
            'data' => AgentResource::collection($page->items())->resolve($request),
            'meta' => $this->meta($page),
        ]);
    }

    /** GET /agents/{agent} — fiche + talents représentés. */
    public function agent(Request $request, Agent $agent): JsonResponse
    {
        abort_unless($agent->is_published, 404);

        $agent->load(['talents' => fn ($q) => $q->where('is_published', true)->orderByTier()]);

        return response()->json([
            'data' => (new AgentResource($agent))->detailed()->resolve($request),
        ]);
    }

    private function meta($page): array
    {
        return [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
        ];
    }
}
