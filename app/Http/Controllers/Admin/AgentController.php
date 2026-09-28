<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesUploads;
use App\Http\Controllers\Admin\Concerns\SavesTranslations;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Agents d'acteurs et de techniciens (cat.md). */
class AgentController extends Controller
{
    use HandlesUploads, SavesTranslations;

    private const TRANSLATABLE = ['bio'];

    public function index(Request $request)
    {
        $query = Agent::withCount('talents')->orderBy('name');

        if (array_key_exists((string) $request->query('represents'), Agent::REPRESENTS)) {
            $query->where('represents', $request->query('represents'));
        }

        return view('agents.index', [
            'agents' => $query->get(),
            'filter' => $request->query('represents'),
        ]);
    }

    public function create()
    {
        return view('agents.create', [
            'agent' => new Agent(['represents' => 'acteurs', 'is_published' => true]),
            'countries' => Country::orderBy('name')->get(['code', 'name', 'flag_emoji']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $agent = Agent::create($this->attributes($request, $data, new Agent()));
        $this->saveEnglish($agent, $request, self::TRANSLATABLE);

        return redirect()->route('agents.index')->with('success', "Agent « {$agent->name} » ajouté.");
    }

    public function edit(Agent $agent)
    {
        $agent->load(['translations', 'talents' => fn ($q) => $q->orderByTier()]);

        return view('agents.edit', [
            'agent' => $agent,
            'countries' => Country::orderBy('name')->get(['code', 'name', 'flag_emoji']),
        ]);
    }

    public function update(Request $request, Agent $agent)
    {
        $data = $this->validated($request);
        $agent->update($this->attributes($request, $data, $agent));
        $this->saveEnglish($agent, $request, self::TRANSLATABLE);

        return back()->with('success', "Agent « {$agent->name} » enregistré.");
    }

    public function destroy(Agent $agent)
    {
        $name = $agent->name;
        $this->forgetPublic($agent->photo_path);
        $agent->translations()->delete();
        // Les talents représentés restent : leur `agent_id` passe à null.
        $agent->delete();

        return redirect()->route('agents.index')->with('success', "Agent « {$name} » supprimé.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:120',
            'agency' => 'nullable|string|max:160',
            'represents' => ['required', Rule::in(array_keys(Agent::REPRESENTS))],
            'email' => 'nullable|email|max:190',
            'phone' => 'nullable|string|max:32',
            'website_url' => 'nullable|url|max:300',
            'country_code' => 'nullable|string|size:2|exists:countries,code',
            'city' => 'nullable|string|max:120',
            'bio' => 'nullable|string|max:4000',
            'photo' => 'nullable|image|max:4096',
            'is_published' => 'required|boolean',
        ] + $this->translationRules(self::TRANSLATABLE));
    }

    private function attributes(Request $request, array $data, Agent $agent): array
    {
        return [
            'name' => $data['name'],
            'agency' => $data['agency'] ?? null,
            'represents' => $data['represents'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'website_url' => $data['website_url'] ?? null,
            'country_code' => $data['country_code'] ?? null,
            'city' => $data['city'] ?? null,
            'bio' => $data['bio'] ?? null,
            'photo_path' => $this->replaceImage($request, 'photo', 'agents', $agent->photo_path),
            'is_published' => (bool) $data['is_published'],
        ];
    }
}
