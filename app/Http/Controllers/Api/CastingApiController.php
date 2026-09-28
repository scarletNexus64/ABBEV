<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CastingCallResource;
use App\Models\CastingApplication;
use App\Models\CastingCall;
use App\Models\CastingRole;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Annonces de casting : consultation publique, candidature avec un compte.
 */
class CastingApiController extends Controller
{
    /**
     * GET /casting-calls — annonces ouvertes d'abord (date limite la plus
     * proche en tête), puis les annonces clôturées récentes.
     * Filtres : `project_type`, `q`, `open=1` (ouvertes seulement).
     */
    public function index(Request $request): JsonResponse
    {
        $query = CastingCall::visible()
            ->withCount('roles')
            ->orderByRaw(
                "CASE WHEN status = 'open' AND (deadline_at IS NULL OR deadline_at > ?) THEN 0 ELSE 1 END",
                [now()]
            )
            ->orderBy('deadline_at')
            ->orderByDesc('published_at');

        if ($request->boolean('open')) {
            $query->acceptingApplications();
        }
        if (array_key_exists((string) $request->query('project_type'), CastingCall::PROJECT_TYPES)) {
            $query->where('project_type', $request->query('project_type'));
        }
        if ($q = trim((string) $request->query('q', ''))) {
            $like = '%' . mb_strtolower($q) . '%';
            $query->where(fn ($s) => $s
                ->whereRaw('LOWER(title) LIKE ?', [$like])
                ->orWhereRaw('LOWER(project_title) LIKE ?', [$like])
                ->orWhereRaw('LOWER(city) LIKE ?', [$like]));
        }

        $page = $query->paginate(min(40, max(5, (int) $request->query('per_page', 20))));

        return response()->json([
            'data' => CastingCallResource::collection($page->items())->resolve($request),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /** GET /casting-calls/{call} — fiche et rôles à pourvoir. */
    public function show(Request $request, CastingCall $call): JsonResponse
    {
        abort_unless(in_array($call->status, ['open', 'closed'], true), 404);

        $call->load('roles')->loadCount('roles');

        $mine = [];
        if ($user = $request->user('sanctum')) {
            $mine = CastingApplication::where('casting_call_id', $call->id)
                ->where('user_id', $user->id)
                ->pluck('status', 'casting_role_id')
                ->all();
        }

        return response()->json([
            'data' => (new CastingCallResource($call))->detailed($mine)->resolve($request),
        ]);
    }

    /**
     * POST /casting-roles/{role}/apply (multipart si photo jointe).
     *
     * La photo est une donnée personnelle : elle va sur le disque PRIVÉ et
     * ne s'affiche que dans l'admin, jamais par une URL publique.
     */
    public function apply(Request $request, CastingRole $role): JsonResponse
    {
        $call = $role->call;

        if (! $call || ! $call->isOpen()) {
            return response()->json(['message' => __('messages.casting.closed')], 422);
        }

        $user = $request->user();

        if (CastingApplication::where('casting_role_id', $role->id)->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => __('messages.casting.already_applied')], 409);
        }

        $data = $request->validate([
            'full_name' => 'required|string|max:120',
            'email' => 'required|email|max:190',
            'phone' => 'nullable|string|max:32',
            'age' => 'nullable|integer|min:1|max:110',
            'city' => 'nullable|string|max:120',
            'message' => 'nullable|string|max:3000',
            'portfolio_url' => 'nullable|url|max:500',
            'photo' => 'nullable|image|max:5120',
        ]);

        $photoPath = $request->hasFile('photo')
            ? $request->file('photo')->store('casting/applications', 'local')
            : null;

        try {
            $application = CastingApplication::create([
                'casting_call_id' => $call->id,
                'casting_role_id' => $role->id,
                'user_id' => $user->id,
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'age' => $data['age'] ?? null,
                'city' => $data['city'] ?? null,
                'message' => $data['message'] ?? null,
                'portfolio_url' => $data['portfolio_url'] ?? null,
                'photo_path' => $photoPath,
                'status' => 'pending',
            ]);
        } catch (UniqueConstraintViolationException) {
            // Double envoi (double tap) : la première candidature a gagné.
            if ($photoPath) {
                Storage::disk('local')->delete($photoPath);
            }

            return response()->json(['message' => __('messages.casting.already_applied')], 409);
        }

        return response()->json([
            'message' => __('messages.casting.applied'),
            'application' => $this->presentApplication($application->load('call', 'role')),
        ], 201);
    }

    /** GET /me/casting-applications */
    public function mine(Request $request): JsonResponse
    {
        $items = CastingApplication::with(['call', 'role'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn (CastingApplication $a) => $this->presentApplication($a));

        return response()->json(['data' => $items]);
    }

    private function presentApplication(CastingApplication $a): array
    {
        return [
            'id' => (int) $a->id,
            'status' => $a->status,
            'created_at' => $a->created_at?->toIso8601String(),
            'role' => $a->role ? ['id' => (int) $a->role->id, 'name' => $a->role->name] : null,
            'call' => $a->call ? [
                'id' => (int) $a->call->id,
                'title' => $a->call->title,
                'project_title' => $a->call->project_title,
                'city' => $a->call->city,
            ] : null,
        ];
    }
}
