<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesUploads;
use App\Http\Controllers\Admin\Concerns\SavesTranslations;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Country;
use App\Models\Media;
use App\Models\Talent;
use App\Models\TalentCredit;
use App\Rules\InWorkspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Annuaire des talents (cat.md : catégories A² → D, bio d'acteur, bio de
 * technicien). Un talent = une fiche publique dans l'app, avec son rang,
 * sa biographie, sa filmographie et son agent.
 */
class TalentController extends Controller
{
    use HandlesUploads, SavesTranslations;

    private const TRANSLATABLE = ['headline', 'bio'];

    public function index(Request $request)
    {
        $query = Talent::with('agent')->orderByTier();

        if (in_array($request->query('kind'), array_keys(Talent::KINDS), true)) {
            $query->where('kind', $request->query('kind'));
        }
        if (in_array($request->query('tier'), Talent::TIERS, true)) {
            $query->where('tier', $request->query('tier'));
        }
        if ($request->query('status') === 'draft') {
            $query->where('is_published', false);
        }
        if ($q = trim((string) $request->query('q', ''))) {
            $like = '%' . mb_strtolower($q) . '%';
            $query->where(fn ($s) => $s
                ->whereRaw('LOWER(first_name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(stage_name) LIKE ?', [$like]));
        }

        $tierCounts = Talent::select('tier', DB::raw('count(*) as n'))->groupBy('tier')->pluck('n', 'tier');

        return view('talents.index', [
            'talents' => $query->paginate(20)->withQueryString(),
            'stats' => [
                'actors' => Talent::where('kind', 'acteur')->count(),
                'technicians' => Talent::where('kind', 'technicien')->count(),
                'drafts' => Talent::where('is_published', false)->count(),
                'agents' => Agent::count(),
            ],
            'tierCounts' => $tierCounts,
            'filters' => $request->only(['kind', 'tier', 'status', 'q']),
        ]);
    }

    public function create()
    {
        return view('talents.create', $this->formData(new Talent([
            'kind' => 'acteur', 'tier' => 'C', 'profession' => 'acteur', 'is_published' => true,
        ])));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $talent = DB::transaction(function () use ($request, $data) {
            $talent = Talent::create($this->attributes($request, $data, new Talent()));
            $this->saveEnglish($talent, $request, self::TRANSLATABLE);
            $this->syncCredits($talent, $data['credits'] ?? []);

            return $talent;
        });

        return redirect()->route('talents.edit', $talent)
            ->with('success', "Fiche de {$talent->displayName()} créée.");
    }

    public function edit(Talent $talent)
    {
        $talent->load(['credits', 'translations']);

        return view('talents.edit', $this->formData($talent));
    }

    public function update(Request $request, Talent $talent)
    {
        $data = $this->validated($request, $talent);

        DB::transaction(function () use ($request, $data, $talent) {
            $talent->update($this->attributes($request, $data, $talent));
            $this->saveEnglish($talent, $request, self::TRANSLATABLE);
            $this->syncCredits($talent, $data['credits'] ?? []);
        });

        return back()->with('success', "Fiche de {$talent->displayName()} enregistrée.");
    }

    public function destroy(Talent $talent)
    {
        $name = $talent->displayName();
        $this->forgetPublic($talent->photo_path);
        $talent->translations()->delete();
        $talent->delete();

        return redirect()->route('talents.index')->with('success', "Fiche de {$name} supprimée.");
    }

    private function formData(Talent $talent): array
    {
        return [
            'talent' => $talent,
            'agents' => Agent::orderBy('name')->get(),
            'countries' => Country::orderBy('name')->get(['code', 'name', 'flag_emoji']),
            'catalog' => Media::approved()->orderBy('title')->get(['id', 'title', 'type', 'release_year']),
        ];
    }

    private function validated(Request $request, ?Talent $talent = null): array
    {
        return $request->validate([
            'kind' => ['required', Rule::in(array_keys(Talent::KINDS))],
            'first_name' => 'required|string|max:80',
            'last_name' => 'required|string|max:80',
            'stage_name' => 'nullable|string|max:120',
            'tier' => ['required', Rule::in(Talent::TIERS)],
            'profession' => ['required', Rule::in(array_keys(Talent::PROFESSIONS))],
            'gender' => ['nullable', Rule::in(array_keys(Talent::GENDERS))],
            'birth_year' => 'nullable|integer|min:1920|max:' . now()->year,
            'country_code' => 'nullable|string|size:2|exists:countries,code',
            'city' => 'nullable|string|max:120',
            'headline' => 'nullable|string|max:160',
            'bio' => 'nullable|string|max:6000',
            'playing_age_min' => 'nullable|integer|min:1|max:100',
            'playing_age_max' => 'nullable|integer|min:1|max:100|gte:playing_age_min',
            'height_cm' => 'nullable|integer|min:50|max:250',
            'languages' => 'nullable|string|max:300',
            'skills' => 'nullable|string|max:500',
            'showreel_url' => 'nullable|url|max:500',
            'agent_id' => ['nullable', 'integer', new InWorkspace(Agent::class)],
            'photo' => 'nullable|image|max:4096',
            'is_published' => 'required|boolean',
            'is_featured' => 'required|boolean',
            'credits' => 'nullable|array|max:60',
            'credits.*.title' => 'nullable|string|max:190',
            'credits.*.year' => 'nullable|integer|min:1900|max:' . (now()->year + 5),
            'credits.*.role' => 'nullable|string|max:190',
            'credits.*.media_id' => ['nullable', 'integer', new InWorkspace(Media::class)],
        ] + $this->translationRules(self::TRANSLATABLE));
    }

    private function attributes(Request $request, array $data, Talent $talent): array
    {
        $isActor = $data['kind'] === 'acteur';

        return [
            'kind' => $data['kind'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'stage_name' => $data['stage_name'] ?? null,
            'tier' => $data['tier'],
            // Un comédien a toujours le métier « acteur » : le menu des
            // postes techniques ne concerne que les techniciens.
            'profession' => $isActor ? 'acteur' : ($data['profession'] === 'acteur' ? 'autre' : $data['profession']),
            'gender' => $data['gender'] ?? null,
            'birth_year' => $data['birth_year'] ?? null,
            'country_code' => $data['country_code'] ?? null,
            'city' => $data['city'] ?? null,
            'headline' => $data['headline'] ?? null,
            'bio' => $data['bio'] ?? null,
            'playing_age_min' => $isActor ? ($data['playing_age_min'] ?? null) : null,
            'playing_age_max' => $isActor ? ($data['playing_age_max'] ?? null) : null,
            'height_cm' => $isActor ? ($data['height_cm'] ?? null) : null,
            'languages' => $this->list($data['languages'] ?? ''),
            'skills' => $this->list($data['skills'] ?? ''),
            'showreel_url' => $data['showreel_url'] ?? null,
            'agent_id' => $data['agent_id'] ?? null,
            'photo_path' => $this->replaceImage($request, 'photo', 'talents', $talent->photo_path),
            'is_published' => (bool) $data['is_published'],
            'is_featured' => (bool) $data['is_featured'],
        ];
    }

    /** « Français, Anglais » → ['Français', 'Anglais'] (sans doublons ni vides). */
    private function list(string $raw): array
    {
        return collect(explode(',', $raw))
            ->map(fn ($v) => trim($v))
            ->filter()
            ->unique(fn ($v) => mb_strtolower($v))
            ->values()
            ->all();
    }

    /** Remplace la filmographie par les lignes du formulaire (lignes vides ignorées). */
    private function syncCredits(Talent $talent, array $credits): void
    {
        $talent->credits()->delete();

        $order = 0;
        foreach ($credits as $row) {
            $mediaId = $row['media_id'] ?? null;
            $title = trim((string) ($row['title'] ?? ''));

            if ($title === '' && $mediaId) {
                $title = (string) Media::whereKey($mediaId)->value('title');
            }
            if ($title === '') {
                continue;
            }

            TalentCredit::create([
                'talent_id' => $talent->id,
                'media_id' => $mediaId ?: null,
                'title' => $title,
                'year' => $row['year'] ?? null,
                'role' => $row['role'] ?? null,
                'sort_order' => $order++,
            ]);
        }
    }
}
