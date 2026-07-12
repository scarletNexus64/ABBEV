<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\DispatchTransferToBunny;
use App\Models\BunnyUpload;
use App\Services\BunnyStreamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Upload de vidéos vers la Bunny Library depuis le dashboard.
 *
 * Phase 1 (ici) : réception chunkée du fichier (navigateur → serveur) via
 * pion/laravel-chunk-upload. Une fois le fichier complet assemblé, on le
 * déplace dans storage/app/public/uploads et on dispatche le Job de transfert.
 *
 * Phase 2 (Job DispatchTransferToBunny) : transfert autonome serveur → Bunny.
 */
class BunnyUploadController extends Controller
{
    /** Extensions vidéo acceptées. */
    private const ALLOWED_EXT = ['mp4', 'mkv', 'mov', 'webm', 'avi', 'm4v', 'ts'];

    public function __construct(protected BunnyStreamService $bunny)
    {
    }

    /** Page d'upload + liste paginée (recherche + filtre côté serveur). */
    public function index(Request $request): View
    {
        $q      = trim((string) $request->get('q', ''));
        $status = (string) $request->get('status', '');

        $query = $this->scopedQuery()->latest();

        if ($q !== '') {
            $query->where('title', 'like', '%'.$q.'%');
        }
        if ($status === 'progress') {
            $query->whereIn('status', ['uploading', 'transferring', 'processing']);
        } elseif (in_array($status, ['queued', 'ready', 'failed'], true)) {
            $query->where('status', $status);
        }

        $uploads = $query->paginate(12)->withQueryString();

        // Uploads bloqués en Phase 1 (aucun chunk reçu depuis 2 min)
        $stalledIds = BunnyUpload::where('status', 'uploading')
            ->where('updated_at', '<', now()->subMinutes(2))
            ->pluck('id')
            ->all();

        return view('admin.bunny.uploads', [
            'uploads'    => $uploads,
            'configured' => $this->bunny->isConfigured(),
            'q'          => $q,
            'status'     => $status,
            'stalledIds' => $stalledIds,
        ]);
    }

    /**
     * JSON des uploads RÉELLEMENT en cours (ni prêts, ni échoués) pour le polling.
     * Les états terminaux sont rendus côté serveur et ne sont pas re-poll.
     */
    public function active(): JsonResponse
    {
        $uploads = $this->scopedQuery()
            ->whereNotIn('status', BunnyUpload::TERMINAL)
            ->latest()
            ->get()
            ->map(fn (BunnyUpload $u) => $this->present($u));

        return response()->json(['data' => $uploads]);
    }

    /** Base de requête cloisonnée : un producteur ne voit QUE ses uploads. */
    private function scopedQuery()
    {
        $query = BunnyUpload::query();
        $user  = auth()->user();
        if ($user && $user->isProducer()) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    /** Un producteur ne peut agir que sur SES uploads. */
    private function authorizeUploadOwnership(BunnyUpload $upload): void
    {
        $user = auth()->user();
        if ($user && $user->isProducer() && $upload->user_id !== $user->id) {
            abort(403, "Cet upload ne vous appartient pas.");
        }
    }

    /**
     * Crée la ligne de suivi avant l'envoi du premier chunk.
     * Retourne l'upload_id que le client renvoie à chaque chunk.
     */
    public function start(Request $request): JsonResponse
    {
        // L'upload est autorisé même si Bunny n'est pas configuré : la vidéo est
        // stockée en local (lisible) et sera transférée vers Bunny plus tard.

        $data = $request->validate([
            'filename'   => 'required|string|max:255',
            'size'       => 'required|integer|min:1',
            'title'      => 'nullable|string|max:255',
            'identifier' => 'nullable|string|max:255',
        ]);

        $ext = strtolower(pathinfo($data['filename'], PATHINFO_EXTENSION));
        if (! in_array($ext, self::ALLOWED_EXT, true)) {
            return response()->json([
                'error' => 'Format non supporté. Formats acceptés : ' . implode(', ', self::ALLOWED_EXT) . '.',
            ], 422);
        }

        // Reprise : si un upload du MÊME fichier (identifiant resumable stable) est
        // déjà en cours de réception, on le réutilise au lieu d'en créer un nouveau.
        // Le client re-déposera le fichier ; les morceaux déjà reçus seront ignorés.
        $identifier = $data['identifier'] ?? null;
        if ($identifier) {
            $existing = BunnyUpload::where('resumable_identifier', $identifier)
                ->where('status', 'uploading')
                ->latest()
                ->first();
            if ($existing) {
                return response()->json(['upload_id' => $existing->id, 'resumed' => true]);
            }
        }

        $title = trim((string) ($data['title'] ?? '')) !== ''
            ? $data['title']
            : pathinfo($data['filename'], PATHINFO_FILENAME);

        $upload = BunnyUpload::create([
            'user_id'              => Auth::id(),
            'original_filename'    => $data['filename'],
            'title'                => $title,
            'size_bytes'           => $data['size'],
            'resumable_identifier' => $identifier,
            'status'               => 'uploading',
            'progress'             => 0,
        ]);

        return response()->json(['upload_id' => $upload->id, 'resumed' => false]);
    }

    /**
     * Réception chunkée (résumable). Chaque morceau est stocké séparément dans
     * storage/app/chunks/{upload_id}/{n}.part. Détection des morceaux déjà reçus
     * (GET de test resumable.js) ⇒ après une coupure réseau ou la fermeture de
     * l'onglet, re-déposer le même fichier reprend là où ça s'était arrêté.
     * Quand tous les morceaux sont là, on assemble le fichier puis on dispatche
     * le transfert vers Bunny.
     */
    public function chunk(Request $request): JsonResponse
    {
        $upload = BunnyUpload::find($request->input('upload_id'));
        if (! $upload) {
            return response()->json(['error' => 'Upload introuvable.'], 404);
        }
        if ($upload->isTerminal() || $upload->local_path) {
            return response()->json(['error' => 'Upload déjà terminé.'], 409);
        }

        $chunkNumber = (int) $request->input('resumableChunkNumber');   // 1-based
        $totalChunks = (int) $request->input('resumableTotalChunks');
        $chunkDir    = storage_path('app/chunks/' . $upload->id);
        $chunkPath   = $chunkDir . '/' . $chunkNumber . '.part';

        // GET : test de présence du morceau (reprise).
        if ($request->isMethod('get')) {
            return is_file($chunkPath)
                ? response()->json(['status' => 'present'], 200)   // déjà reçu → resumable saute
                : response()->json(['status' => 'absent'], 204);   // à envoyer
        }

        // POST : réception du morceau.
        if (! $request->hasFile('file')) {
            return response()->json(['error' => 'Morceau manquant.'], 422);
        }
        if (! is_dir($chunkDir)) {
            @mkdir($chunkDir, 0775, true);
        }
        $request->file('file')->move($chunkDir, $chunkNumber . '.part');

        $received = count(glob($chunkDir . '/*.part') ?: []);
        $percent  = $totalChunks > 0 ? (int) floor($received / $totalChunks * 100) : 0;
        $upload->update([
            'bytes_received' => (int) round($upload->size_bytes * $percent / 100),
            'progress'       => $percent,
        ]);

        // Tous les morceaux reçus → on délègue l'ASSEMBLAGE au worker (verrou anti-double).
        // L'assemblage (concaténation, potentiellement longue pour un gros fichier) ne se
        // fait PAS dans la requête web : elle répond tout de suite, le worker assemble puis
        // transfère. La réception ne pénalise donc jamais le service.
        if ($totalChunks > 0 && $received >= $totalChunks) {
            $lock = Cache::lock('bunny-finalize-' . $upload->id, 60);
            if ($lock->get()) {
                try {
                    $fresh = $upload->fresh();
                    if ($fresh->status === 'uploading' && ! $fresh->local_path) {
                        $upload->update([
                            'status'         => 'queued',
                            'progress'       => 100,
                            'bytes_received' => $upload->size_bytes,
                            'uploaded_at'    => now(),
                        ]);
                        DispatchTransferToBunny::dispatch($upload->id);
                    }
                } finally {
                    $lock->release();
                }
            }

            return response()->json(['status' => 'queued', 'done' => 100, 'uploadId' => $upload->id]);
        }

        return response()->json([
            'status'   => 'uploading',
            'done'     => $percent,
            'uploadId' => $upload->id,
        ]);
    }

    /** Statut JSON d'un upload (polling UI). */
    public function status(BunnyUpload $upload): JsonResponse
    {
        $this->authorizeUploadOwnership($upload);

        return response()->json($this->present($upload));
    }

    /**
     * Télécharge l'original conservé en local (filet de secours si Bunny échoue).
     * Disponible tant que le fichier temporaire existe (uploads non transférés/échoués).
     */
    public function download(BunnyUpload $upload): mixed
    {
        $this->authorizeUploadOwnership($upload);

        $path = $upload->localFilePath();
        if (! $path) {
            abort(404, 'Fichier original non disponible (déjà transféré vers Bunny ou supprimé).');
        }

        return response()->download($path, $upload->original_filename);
    }

    /**
     * Prévisualisation admin de la vidéo locale (disque privé). Sert le fichier
     * en streaming (support Range) — remplace l'ancien lien /storage direct.
     */
    public function stream(BunnyUpload $upload): mixed
    {
        $this->authorizeUploadOwnership($upload);

        $path = $upload->localFilePath();
        if (! $path) {
            abort(404, 'Vidéo locale non disponible.');
        }

        return response()->file($path);
    }

    /**
     * Relance le transfert vers Bunny d'un upload échoué (après correction de la clé API).
     * Possible uniquement si l'original local est encore présent.
     */
    public function retry(BunnyUpload $upload): JsonResponse
    {
        $this->authorizeUploadOwnership($upload);

        if ($upload->status !== 'failed') {
            return response()->json(['error' => 'Seuls les uploads en échec peuvent être relancés.'], 422);
        }
        if (! $upload->localFilePath()) {
            return response()->json(['error' => 'Fichier original introuvable : impossible de relancer.'], 422);
        }

        $upload->update([
            'status'       => 'queued',
            'error'        => null,
            'progress'     => 100,
            'bytes_sent'   => 0,
            'bunny_status' => null,
        ]);

        DispatchTransferToBunny::dispatch($upload->id);

        return response()->json($this->present($upload));
    }

    /** Supprime un upload (fichier local + morceaux + ligne). */
    public function destroy(BunnyUpload $upload): JsonResponse
    {
        $this->authorizeUploadOwnership($upload);

        $res = $this->deleteOne($upload);

        return response()->json($res, $res['deleted'] ? 200 : 422);
    }

    /** Supprime plusieurs uploads d'un coup (sélection multiple). */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $ids = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer',
        ])['ids'];

        $deleted = 0;
        $skipped = [];

        foreach ($this->scopedQuery()->whereIn('id', $ids)->get() as $upload) {
            $res = $this->deleteOne($upload);
            if ($res['deleted']) {
                $deleted++;
            } else {
                $skipped[] = ['id' => $upload->id, 'title' => $upload->title, 'reason' => $res['reason']];
            }
        }

        return response()->json(['deleted' => $deleted, 'skipped' => $skipped]);
    }

    /* ---------------------------------------------------------------
     |  Helpers
     * --------------------------------------------------------------- */

    /**
     * Supprime un upload et ses fichiers. Refuse si la vidéo locale est encore
     * rattachée à un film ou un épisode (pour ne pas casser une lecture).
     *
     * @return array{deleted: bool, reason?: string}
     */
    private function deleteOne(BunnyUpload $upload): array
    {
        if ($upload->local_path) {
            $media = \App\Models\Media::where('video_provider', 'local')->where('video_path', $upload->local_path)->first();
            $ep    = \App\Models\Episode::where('video_provider', 'local')->where('video_path', $upload->local_path)->first();
            if ($media || $ep) {
                $where = $media ? 'le film « '.$media->title.' »'
                    : 'un épisode de « '.optional(optional($ep->season)->media)->title.' »';

                return ['deleted' => false, 'reason' => 'Utilisée par '.$where.' — change/retire sa vidéo d’abord.'];
            }
        }

        // Fichiers (vidéo assemblée privée + chemins éventuels) et morceaux.
        $paths = [
            $upload->temp_path,
            $upload->local_path ? \Illuminate\Support\Facades\Storage::disk('local')->path($upload->local_path) : null,
        ];
        foreach ($paths as $p) {
            if ($p && is_file($p)) {
                @unlink($p);
            }
        }
        \Illuminate\Support\Facades\File::deleteDirectory(storage_path('app/chunks/' . $upload->id));

        $upload->delete();
        Cache::forget('bunny.videos.all');

        return ['deleted' => true];
    }

    private function present(BunnyUpload $upload): array
    {
        $hasLocalFile = $upload->localFilePath() !== null;

        return [
            'id'             => $upload->id,
            'title'          => $upload->title,
            'filename'       => $upload->original_filename,
            'status'         => $upload->status,
            'progress'       => $upload->progress,
            'size_bytes'     => $upload->size_bytes,
            'bytes_received' => $upload->bytes_received,
            'bytes_sent'     => $upload->bytes_sent,
            'bunny_status'   => $upload->bunny_status,
            'bunny_guid'     => $upload->bunny_guid,
            'error'          => $upload->error,
            'created_at'     => $upload->created_at?->toIso8601String(),
            'has_local_file' => $hasLocalFile,
            'download_url'   => $hasLocalFile ? route('admin.bunny.uploads.download', $upload->id) : null,
            'can_retry'      => $hasLocalFile && $upload->status === 'failed',
            // Disponibilité locale automatique (lisible tant que Bunny n'a pas pris le relais)
            'local_ready'    => $upload->hasLocalCopy(),
            'local_url'      => $upload->hasLocalCopy() ? asset('storage/' . $upload->local_path) : null,
        ];
    }
}
