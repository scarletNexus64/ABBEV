<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesUploads;
use App\Http\Controllers\Admin\Concerns\SavesTranslations;
use App\Http\Controllers\Controller;
use App\Models\CastingApplication;
use App\Models\CastingCall;
use App\Models\CastingRole;
use App\Models\Country;
use App\Models\Talent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Annonces de casting (cat.md : « annonce casting ») : le projet, ses rôles
 * à pourvoir, puis la revue des candidatures reçues dans l'app.
 */
class CastingCallController extends Controller
{
    use HandlesUploads, SavesTranslations;

    private const TRANSLATABLE = ['description'];

    public function index(Request $request)
    {
        $status = $request->query('status');

        $calls = CastingCall::query()
            ->withCount([
                'roles',
                'applications',
                'applications as pending_count' => fn ($q) => $q->where('status', 'pending'),
            ])
            ->when(in_array($status, array_keys(CastingCall::STATUSES), true), fn ($q) => $q->where('status', $status))
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'draft' THEN 1 ELSE 2 END")
            ->orderByDesc('created_at')
            ->get();

        return view('castings.index', [
            'calls' => $calls,
            'status' => $status,
            'stats' => [
                'open' => CastingCall::acceptingApplications()->count(),
                'applications' => CastingApplication::count(),
                'pending' => CastingApplication::where('status', 'pending')->count(),
                'shortlisted' => CastingApplication::where('status', 'shortlisted')->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('castings.create', $this->formData(new CastingCall([
            'project_type' => 'film', 'compensation' => 'remunere', 'status' => 'draft', 'country_code' => 'CM',
        ])));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $call = DB::transaction(function () use ($request, $data) {
            $call = CastingCall::create($this->attributes($request, $data, new CastingCall()));
            $this->saveEnglish($call, $request, self::TRANSLATABLE);
            $this->syncRoles($call, $data['roles'] ?? []);

            return $call;
        });

        return redirect()->route('castings.show', $call)->with('success', 'Annonce créée.');
    }

    public function show(Request $request, CastingCall $casting)
    {
        $casting->load(['roles' => fn ($q) => $q->withCount('applications')]);

        $filter = $request->query('status');
        $applications = $casting->applications()
            ->with(['role', 'user'])
            ->when(array_key_exists((string) $filter, CastingApplication::STATUSES), fn ($q) => $q->where('status', $filter))
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'shortlisted' THEN 1 WHEN 'accepted' THEN 2 ELSE 3 END")
            ->latest()
            ->get()
            ->groupBy('casting_role_id');

        return view('castings.show', [
            'call' => $casting,
            'applicationsByRole' => $applications,
            'filter' => $filter,
            'counts' => $casting->applications()->select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function edit(CastingCall $casting)
    {
        $casting->load(['roles', 'translations']);

        return view('castings.edit', $this->formData($casting));
    }

    public function update(Request $request, CastingCall $casting)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($request, $data, $casting) {
            $casting->update($this->attributes($request, $data, $casting));
            $this->saveEnglish($casting, $request, self::TRANSLATABLE);
            $this->syncRoles($casting, $data['roles'] ?? []);
        });

        return redirect()->route('castings.show', $casting)->with('success', 'Annonce enregistrée.');
    }

    public function destroy(CastingCall $casting)
    {
        foreach ($casting->applications()->whereNotNull('photo_path')->pluck('photo_path') as $path) {
            Storage::disk('local')->delete($path);
        }
        $this->forgetPublic($casting->cover_path);
        $casting->translations()->delete();
        $casting->delete();

        return redirect()->route('castings.index')->with('success', 'Annonce supprimée, ainsi que ses candidatures.');
    }

    /** PATCH — statut et note interne d'une candidature. */
    public function review(Request $request, CastingCall $casting, CastingApplication $application)
    {
        abort_unless($application->casting_call_id === $casting->id, 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(CastingApplication::STATUSES))],
            'admin_note' => 'nullable|string|max:2000',
        ]);

        $application->update($data + ['reviewed_at' => now()]);

        return back()->with('success', "Candidature de {$application->full_name} : " . mb_strtolower($application->statusLabel()) . '.');
    }

    /** Photo jointe à une candidature (disque privé, admin seulement). */
    public function photo(CastingCall $casting, CastingApplication $application)
    {
        abort_unless($application->casting_call_id === $casting->id && $application->photo_path, 404);
        abort_unless(Storage::disk('local')->exists($application->photo_path), 404);

        return response()->file(Storage::disk('local')->path($application->photo_path));
    }

    /** Export CSV des candidatures (ouvrable dans Excel). */
    public function export(CastingCall $casting): StreamedResponse
    {
        $rows = $casting->applications()->with('role')->orderBy('casting_role_id')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM : accents lisibles dans Excel
            fputcsv($out, ['Rôle', 'Nom', 'E-mail', 'Téléphone', 'Âge', 'Ville', 'Lien', 'Statut', 'Message', 'Reçue le'], ';');
            foreach ($rows as $a) {
                fputcsv($out, [
                    $a->role?->name, $a->full_name, $a->email, $a->phone, $a->age, $a->city,
                    $a->portfolio_url, $a->statusLabel(), $a->message, $a->created_at?->format('d/m/Y H:i'),
                ], ';');
            }
            fclose($out);
        }, 'candidatures-' . $casting->slug . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function formData(CastingCall $call): array
    {
        return [
            'call' => $call,
            'countries' => Country::orderBy('name')->get(['code', 'name', 'flag_emoji']),
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:190',
            'project_title' => 'required|string|max:190',
            'project_type' => ['required', Rule::in(array_keys(CastingCall::PROJECT_TYPES))],
            'production_company' => 'nullable|string|max:190',
            'director' => 'nullable|string|max:190',
            'description' => 'nullable|string|max:8000',
            'city' => 'nullable|string|max:120',
            'country_code' => 'nullable|string|size:2|exists:countries,code',
            'shooting_starts_on' => 'nullable|date',
            'shooting_ends_on' => 'nullable|date|after_or_equal:shooting_starts_on',
            'deadline_at' => 'nullable|date',
            'compensation' => ['required', Rule::in(array_keys(CastingCall::COMPENSATIONS))],
            'compensation_details' => 'nullable|string|max:190',
            'contact_email' => 'nullable|email|max:190',
            'status' => ['required', Rule::in(array_keys(CastingCall::STATUSES))],
            'is_featured' => 'required|boolean',
            'cover' => 'nullable|image|max:4096',
            'roles' => 'nullable|array|max:40',
            'roles.*.id' => 'nullable|integer',
            'roles.*.name' => 'nullable|string|max:160',
            'roles.*.kind' => ['nullable', Rule::in(array_keys(Talent::KINDS))],
            'roles.*.profession' => ['nullable', Rule::in(array_keys(Talent::PROFESSIONS))],
            'roles.*.importance' => ['nullable', Rule::in(array_keys(CastingRole::IMPORTANCE))],
            'roles.*.gender' => ['nullable', Rule::in(array_keys(CastingRole::GENDERS))],
            'roles.*.age_min' => 'nullable|integer|min:1|max:100',
            'roles.*.age_max' => 'nullable|integer|min:1|max:100',
            'roles.*.min_tier' => ['nullable', Rule::in(Talent::TIERS)],
            'roles.*.description' => 'nullable|string|max:2000',
            'roles.*.requirements' => 'nullable|string|max:1000',
            'roles.*.positions' => 'nullable|integer|min:1|max:500',
        ] + $this->translationRules(self::TRANSLATABLE));
    }

    private function attributes(Request $request, array $data, CastingCall $call): array
    {
        $status = $data['status'];

        return [
            'title' => $data['title'],
            'project_title' => $data['project_title'],
            'project_type' => $data['project_type'],
            'production_company' => $data['production_company'] ?? null,
            'director' => $data['director'] ?? null,
            'description' => $data['description'] ?? null,
            'city' => $data['city'] ?? null,
            'country_code' => $data['country_code'] ?? null,
            'shooting_starts_on' => $data['shooting_starts_on'] ?? null,
            'shooting_ends_on' => $data['shooting_ends_on'] ?? null,
            'deadline_at' => $data['deadline_at'] ?? null,
            'compensation' => $data['compensation'],
            'compensation_details' => $data['compensation_details'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'status' => $status,
            'is_featured' => (bool) $data['is_featured'],
            // Date de première ouverture : fixée au passage en « ouverte ».
            'published_at' => $status === 'open' ? ($call->published_at ?? now()) : $call->published_at,
            'cover_path' => $this->replaceImage($request, 'cover', 'casting', $call->cover_path),
        ];
    }

    /**
     * Met les rôles en conformité avec le formulaire. Un rôle existant garde
     * son id (et donc ses candidatures) ; un rôle retiré du formulaire est
     * supprimé AVEC ses candidatures — le formulaire le signale.
     */
    private function syncRoles(CastingCall $call, array $rows): void
    {
        $kept = [];

        foreach (array_values($rows) as $order => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $kind = $row['kind'] ?? 'acteur';
            $attributes = [
                'name' => $name,
                'kind' => $kind,
                'profession' => $kind === 'technicien' ? ($row['profession'] ?? null) : null,
                'importance' => $kind === 'acteur' ? ($row['importance'] ?? null) : null,
                'gender' => $row['gender'] ?? 'indifferent',
                'age_min' => $row['age_min'] ?? null,
                'age_max' => $row['age_max'] ?? null,
                'min_tier' => $row['min_tier'] ?? null,
                'description' => $row['description'] ?? null,
                'requirements' => $row['requirements'] ?? null,
                'positions' => max(1, (int) ($row['positions'] ?? 1)),
                'sort_order' => $order,
            ];

            $role = ! empty($row['id'])
                ? $call->roles()->whereKey($row['id'])->first()
                : null;

            if ($role) {
                $role->update($attributes);
            } else {
                $role = $call->roles()->create($attributes);
            }
            $kept[] = $role->id;
        }

        $call->roles()->whereNotIn('id', $kept)->delete();
    }
}
