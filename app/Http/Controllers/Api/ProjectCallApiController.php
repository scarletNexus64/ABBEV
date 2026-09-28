<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectCallResource;
use App\Models\ProjectCall;
use App\Models\ProjectPledge;
use App\Models\ProjectSubmission;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Appels à projets côté app : financement (promesses de soutien), écriture
 * de scénario et musique (candidatures).
 */
class ProjectCallApiController extends Controller
{
    /**
     * GET /calls — filtres `type` et `target`. Appels ouverts d'abord, date
     * de clôture la plus proche en tête.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ProjectCall::visible()
            ->ofType($request->query('type'))
            ->with(['pledges' => fn ($q) => $q->where('status', 'confirmed')])
            ->withCount('submissions')
            ->orderByRaw(
                "CASE WHEN status = 'open' AND (closes_at IS NULL OR closes_at > ?) THEN 0 ELSE 1 END",
                [now()]
            )
            ->orderBy('closes_at')
            ->orderByDesc('id');

        if ($target = $request->query('target')) {
            $query->where('target', $target);
        }

        return response()->json([
            'data' => ProjectCallResource::collection($query->get())->resolve($request),
        ]);
    }

    /** GET /calls/{call} */
    public function show(Request $request, ProjectCall $call): JsonResponse
    {
        abort_unless(in_array($call->status, ['open', 'closed', 'completed'], true), 404);

        $call->load(['pledges' => fn ($q) => $q->where('status', 'confirmed')])
            ->loadCount('submissions');

        return response()->json([
            'data' => (new ProjectCallResource($call))
                ->detailed($this->participationOf($request, $call))
                ->resolve($request),
        ]);
    }

    /** POST /calls/{call}/submissions — écriture ou musique (multipart si PDF). */
    public function submit(Request $request, ProjectCall $call): JsonResponse
    {
        if ($call->isFunding()) {
            return response()->json(['message' => __('messages.calls.no_funding')], 422);
        }
        if (! $call->isOpen()) {
            return response()->json(['message' => __('messages.calls.closed')], 422);
        }

        $user = $request->user();
        if (ProjectSubmission::where('project_call_id', $call->id)->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => __('messages.calls.already_submitted')], 409);
        }

        $isMusic = $call->type === 'musique';

        $data = $request->validate([
            'title' => 'required|string|max:190',
            'logline' => 'nullable|string|max:500',
            // Écriture : le scénario en PDF OU, à défaut, un synopsis.
            'synopsis' => [Rule::requiredIf(! $isMusic && ! $request->hasFile('file')), 'nullable', 'string', 'max:6000'],
            'message' => 'nullable|string|max:3000',
            'phone' => 'nullable|string|max:32',
            // Musique : un lien d'écoute est indispensable.
            'link_url' => [$isMusic ? 'required' : 'nullable', 'url', 'max:500'],
            'file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $path = $request->hasFile('file')
            ? $request->file('file')->store('calls/submissions', 'local')
            : null;

        try {
            $submission = ProjectSubmission::create([
                'project_call_id' => $call->id,
                'user_id' => $user->id,
                'title' => $data['title'],
                'logline' => $data['logline'] ?? null,
                'synopsis' => $data['synopsis'] ?? null,
                'message' => $data['message'] ?? null,
                'phone' => $data['phone'] ?? null,
                'link_url' => $data['link_url'] ?? null,
                'file_path' => $path,
                'status' => 'received',
            ]);
        } catch (UniqueConstraintViolationException) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }

            return response()->json(['message' => __('messages.calls.already_submitted')], 409);
        }

        return response()->json([
            'message' => __('messages.calls.submitted'),
            'participation' => $this->presentSubmission($submission),
        ], 201);
    }

    /** POST /calls/{call}/pledges — promesse de soutien (financement). */
    public function pledge(Request $request, ProjectCall $call): JsonResponse
    {
        if (! $call->isFunding()) {
            return response()->json(['message' => __('messages.calls.funding_only')], 422);
        }
        if (! $call->isOpen()) {
            return response()->json(['message' => __('messages.calls.closed')], 422);
        }

        $min = max(1.0, (float) ($call->min_pledge ?? 0));
        $rewardTitles = collect($call->rewards ?? [])->pluck('title')->filter()->values()->all();

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'max:1000000000'],
            'reward_title' => ['nullable', 'string', Rule::in($rewardTitles)],
            'message' => 'nullable|string|max:2000',
            'phone' => 'required|string|max:32',
            'is_anonymous' => 'nullable|boolean',
        ]);

        if ((float) $data['amount'] < $min) {
            return response()->json([
                'message' => __('messages.calls.pledge_min', ['amount' => Money::format($min, $call->currency)]),
                'errors' => ['amount' => [__('messages.calls.pledge_min', ['amount' => Money::format($min, $call->currency)])]],
            ], 422);
        }

        $pledge = ProjectPledge::create([
            'project_call_id' => $call->id,
            'user_id' => $request->user()->id,
            'amount' => $data['amount'],
            'currency' => $call->currency,
            'reward_title' => $data['reward_title'] ?? null,
            'message' => $data['message'] ?? null,
            'phone' => $data['phone'],
            'is_anonymous' => (bool) ($data['is_anonymous'] ?? false),
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => __('messages.calls.pledged'),
            'participation' => $this->presentPledge($pledge),
        ], 201);
    }

    /** GET /me/calls — mes candidatures et mes promesses. */
    public function mine(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $submissions = ProjectSubmission::with('call')->where('user_id', $userId)->latest()->get()
            ->map(fn ($s) => $this->presentSubmission($s) + ['call' => $this->callSummary($s->call)]);
        $pledges = ProjectPledge::with('call')->where('user_id', $userId)->latest()->get()
            ->map(fn ($p) => $this->presentPledge($p) + ['call' => $this->callSummary($p->call)]);

        return response()->json(['data' => $submissions->concat($pledges)
            ->sortByDesc('created_at')->values()]);
    }

    private function participationOf(Request $request, ProjectCall $call): ?array
    {
        $user = $request->user('sanctum');
        if (! $user) {
            return null;
        }

        if ($call->isFunding()) {
            $pledge = ProjectPledge::where('project_call_id', $call->id)
                ->where('user_id', $user->id)
                ->where('status', '!=', 'cancelled')
                ->latest()
                ->first();

            return $pledge ? $this->presentPledge($pledge) : null;
        }

        $submission = ProjectSubmission::where('project_call_id', $call->id)
            ->where('user_id', $user->id)
            ->first();

        return $submission ? $this->presentSubmission($submission) : null;
    }

    private function presentSubmission(ProjectSubmission $s): array
    {
        return [
            'kind' => 'submission',
            'id' => (int) $s->id,
            'status' => $s->status,
            'title' => $s->title,
            'created_at' => $s->created_at?->toIso8601String(),
        ];
    }

    private function presentPledge(ProjectPledge $p): array
    {
        $money = Money::meta($p->currency);

        return [
            'kind' => 'pledge',
            'id' => (int) $p->id,
            'status' => $p->status,
            'amount' => (float) $p->amount,
            'currency' => $p->currency,
            'currency_symbol' => $money['symbol'],
            'currency_decimals' => $money['decimals'],
            'reward_title' => $p->reward_title,
            'created_at' => $p->created_at?->toIso8601String(),
        ];
    }

    private function callSummary(?ProjectCall $call): ?array
    {
        return $call ? [
            'id' => (int) $call->id,
            'type' => $call->type,
            'target' => $call->target,
            'title' => $call->t('title'),
        ] : null;
    }
}
