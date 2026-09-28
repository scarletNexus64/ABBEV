<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesUploads;
use App\Http\Controllers\Admin\Concerns\SavesTranslations;
use App\Http\Controllers\Controller;
use App\Models\ProjectCall;
use App\Models\ProjectPledge;
use App\Models\ProjectSubmission;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Appels à projets de cat.md :
 *  - financement (film, série & feuilleton, documentaire) → promesses de
 *    soutien, que l'équipe confirme à réception des fonds ;
 *  - écriture de scénario (mêmes cibles) et musique (cinéma, télévision)
 *    → candidatures, qu'elle présélectionne puis désigne lauréates.
 */
class ProjectCallController extends Controller
{
    use HandlesUploads, SavesTranslations;

    private const TRANSLATABLE = ['title', 'summary', 'description', 'requirements'];

    public function index(Request $request)
    {
        $type = array_key_exists((string) $request->query('type'), ProjectCall::TYPES) ? $request->query('type') : null;

        $calls = ProjectCall::query()
            ->ofType($type)
            ->with(['pledges' => fn ($q) => $q->where('status', 'confirmed')])
            ->withCount([
                'submissions',
                'submissions as new_submissions' => fn ($q) => $q->where('status', 'received'),
                'pledges as pending_pledges' => fn ($q) => $q->where('status', 'pending'),
            ])
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'draft' THEN 1 ELSE 2 END")
            ->orderBy('closes_at')
            ->get();

        return view('calls.index', [
            'calls' => $calls,
            'type' => $type,
            'stats' => [
                'open' => ProjectCall::where('status', 'open')->count(),
                'submissions' => ProjectSubmission::where('status', 'received')->count(),
                'pledges' => ProjectPledge::where('status', 'pending')->count(),
                'confirmed' => ProjectPledge::where('status', 'confirmed')->sum('amount'),
            ],
        ]);
    }

    public function create(Request $request)
    {
        $type = array_key_exists((string) $request->query('type'), ProjectCall::TYPES) ? $request->query('type') : 'financement';

        return view('calls.create', ['call' => new ProjectCall([
            'type' => $type,
            'target' => array_key_first(ProjectCall::TARGETS[$type]),
            'status' => 'draft',
            'currency' => 'XAF',
        ])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $call = ProjectCall::create($this->attributes($request, $data, new ProjectCall()));
        $this->saveEnglish($call, $request, self::TRANSLATABLE);

        return redirect()->route('calls.show', $call)->with('success', 'Appel créé.');
    }

    public function show(Request $request, ProjectCall $call)
    {
        $status = $request->query('status');

        if ($call->isFunding()) {
            $pledges = $call->pledges()->with('user')
                ->when(array_key_exists((string) $status, ProjectPledge::STATUSES), fn ($q) => $q->where('status', $status))
                ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'confirmed' THEN 1 ELSE 2 END")
                ->latest()
                ->get();

            return view('calls.show', [
                'call' => $call,
                'status' => $status,
                'pledges' => $pledges,
                'submissions' => collect(),
                'counts' => $call->pledges()->select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status'),
                'pendingAmount' => (float) $call->pledges()->where('status', 'pending')->sum('amount'),
            ]);
        }

        $submissions = $call->submissions()->with('user')
            ->when(array_key_exists((string) $status, ProjectSubmission::STATUSES), fn ($q) => $q->where('status', $status))
            ->orderByRaw("CASE status WHEN 'received' THEN 0 WHEN 'shortlisted' THEN 1 WHEN 'selected' THEN 2 ELSE 3 END")
            ->latest()
            ->get();

        return view('calls.show', [
            'call' => $call,
            'status' => $status,
            'pledges' => collect(),
            'submissions' => $submissions,
            'counts' => $call->submissions()->select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status'),
            'pendingAmount' => 0,
        ]);
    }

    public function edit(ProjectCall $call)
    {
        $call->load('translations');

        return view('calls.edit', ['call' => $call]);
    }

    public function update(Request $request, ProjectCall $call)
    {
        $data = $this->validated($request, $call);
        $call->update($this->attributes($request, $data, $call));
        $this->saveEnglish($call, $request, self::TRANSLATABLE);

        return redirect()->route('calls.show', $call)->with('success', 'Appel enregistré.');
    }

    public function destroy(ProjectCall $call)
    {
        foreach ($call->submissions()->whereNotNull('file_path')->pluck('file_path') as $path) {
            Storage::disk('local')->delete($path);
        }
        $this->forgetPublic($call->cover_path);
        $call->translations()->delete();
        $call->delete();

        return redirect()->route('calls.index')->with('success', 'Appel supprimé, avec ses participations.');
    }

    public function reviewSubmission(Request $request, ProjectCall $call, ProjectSubmission $submission)
    {
        abort_unless($submission->project_call_id === $call->id, 404);

        $submission->update($request->validate([
            'status' => ['required', Rule::in(array_keys(ProjectSubmission::STATUSES))],
            'admin_note' => 'nullable|string|max:2000',
        ]) + ['reviewed_at' => now()]);

        return back()->with('success', "« {$submission->title} » : " . mb_strtolower($submission->statusLabel()) . '.');
    }

    public function submissionFile(ProjectCall $call, ProjectSubmission $submission)
    {
        abort_unless($submission->project_call_id === $call->id && $submission->file_path, 404);
        abort_unless(Storage::disk('local')->exists($submission->file_path), 404);

        return response()->file(Storage::disk('local')->path($submission->file_path), ['Content-Type' => 'application/pdf']);
    }

    /** Confirme (fonds reçus) ou annule une promesse de soutien. */
    public function reviewPledge(Request $request, ProjectCall $call, ProjectPledge $pledge)
    {
        abort_unless($pledge->project_call_id === $call->id, 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(ProjectPledge::STATUSES))],
            'admin_note' => 'nullable|string|max:2000',
        ]);

        $pledge->update($data + [
            'confirmed_at' => $data['status'] === 'confirmed' ? ($pledge->confirmed_at ?? now()) : null,
        ]);

        return back()->with('success', 'Promesse de ' . Money::format($pledge->amount, $pledge->currency) . ' : ' . mb_strtolower($pledge->statusLabel()) . '.');
    }

    /** Export CSV des participations (candidatures ou promesses). */
    public function export(ProjectCall $call): StreamedResponse
    {
        $funding = $call->isFunding();
        $rows = $funding
            ? $call->pledges()->with('user')->latest()->get()
            : $call->submissions()->with('user')->latest()->get();

        return response()->streamDownload(function () use ($rows, $funding) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            if ($funding) {
                fputcsv($out, ['Soutien', 'E-mail', 'Téléphone', 'Montant', 'Devise', 'Contrepartie', 'Anonyme', 'Statut', 'Message', 'Date'], ';');
                foreach ($rows as $p) {
                    fputcsv($out, [$p->user?->name, $p->user?->email, $p->phone, $p->amount, $p->currency, $p->reward_title,
                        $p->is_anonymous ? 'oui' : 'non', $p->statusLabel(), $p->message, $p->created_at?->format('d/m/Y H:i')], ';');
                }
            } else {
                fputcsv($out, ['Auteur', 'E-mail', 'Téléphone', 'Titre', 'Pitch', 'Lien', 'Statut', 'Date'], ';');
                foreach ($rows as $s) {
                    fputcsv($out, [$s->user?->name, $s->user?->email, $s->phone, $s->title, $s->logline, $s->link_url,
                        $s->statusLabel(), $s->created_at?->format('d/m/Y H:i')], ';');
                }
            }
            fclose($out);
        }, 'participations-' . $call->slug . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ------------------------------------------------------------------

    private function validated(Request $request, ?ProjectCall $call = null): array
    {
        $type = $request->input('type');
        $targets = array_keys(ProjectCall::TARGETS[$type] ?? []);

        return $request->validate([
            'type' => ['required', Rule::in(array_keys(ProjectCall::TYPES))],
            'target' => ['required', Rule::in($targets)],
            'title' => 'required|string|max:190',
            'summary' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:8000',
            'organizer' => 'nullable|string|max:190',
            'opens_at' => 'nullable|date',
            'closes_at' => 'nullable|date|after_or_equal:opens_at',
            'status' => ['required', Rule::in(array_keys(ProjectCall::STATUSES))],
            'is_featured' => 'required|boolean',
            'cover' => 'nullable|image|max:4096',
            'rules_url' => 'nullable|url|max:500',
            'contact_email' => 'nullable|email|max:190',
            // Financement
            'goal_amount' => 'nullable|required_if:type,financement|numeric|min:1',
            'currency' => 'nullable|string|size:3',
            'min_pledge' => 'nullable|numeric|min:0',
            'raised_offline' => 'nullable|numeric|min:0',
            'rewards' => 'nullable|array|max:12',
            'rewards.*.amount' => 'nullable|numeric|min:0',
            'rewards.*.title' => 'nullable|string|max:120',
            'rewards.*.description' => 'nullable|string|max:300',
            // Écriture & musique
            'requirements' => 'nullable|string|max:3000',
            'prize' => 'nullable|string|max:190',
            'genre' => 'nullable|string|max:120',
            'max_pages' => 'nullable|integer|min:1|max:1000',
            'music_style' => 'nullable|string|max:190',
            'max_duration_minutes' => 'nullable|integer|min:1|max:600',
        ] + $this->translationRules(self::TRANSLATABLE));
    }

    private function attributes(Request $request, array $data, ProjectCall $call): array
    {
        $funding = $data['type'] === 'financement';
        $status = $data['status'];

        return [
            'type' => $data['type'],
            'target' => $data['target'],
            'title' => $data['title'],
            'summary' => $data['summary'] ?? null,
            'description' => $data['description'] ?? null,
            'organizer' => $data['organizer'] ?? null,
            'opens_at' => $data['opens_at'] ?? null,
            'closes_at' => $data['closes_at'] ?? null,
            'status' => $status,
            'is_featured' => (bool) $data['is_featured'],
            'published_at' => $status === 'open' ? ($call->published_at ?? now()) : $call->published_at,
            'cover_path' => $this->replaceImage($request, 'cover', 'calls', $call->cover_path),
            'rules_url' => $data['rules_url'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'goal_amount' => $funding ? ($data['goal_amount'] ?? null) : null,
            'currency' => strtoupper($data['currency'] ?? 'XAF'),
            'min_pledge' => $funding ? ($data['min_pledge'] ?? null) : null,
            'raised_offline' => $funding ? ($data['raised_offline'] ?? 0) : 0,
            'rewards' => $funding
                ? collect($data['rewards'] ?? [])
                    ->filter(fn ($r) => trim((string) ($r['title'] ?? '')) !== '')
                    ->map(fn ($r) => [
                        'amount' => isset($r['amount']) && $r['amount'] !== '' ? (float) $r['amount'] : null,
                        'title' => trim($r['title']),
                        'description' => trim((string) ($r['description'] ?? '')),
                    ])
                    ->sortBy('amount')
                    ->values()
                    ->all()
                : null,
            'requirements' => $funding ? null : ($data['requirements'] ?? null),
            'prize' => $funding ? null : ($data['prize'] ?? null),
            'genre' => $data['type'] === 'ecriture' ? ($data['genre'] ?? null) : null,
            'max_pages' => $data['type'] === 'ecriture' ? ($data['max_pages'] ?? null) : null,
            'music_style' => $data['type'] === 'musique' ? ($data['music_style'] ?? null) : null,
            'max_duration_minutes' => $data['type'] === 'musique' ? ($data['max_duration_minutes'] ?? null) : null,
        ];
    }
}
