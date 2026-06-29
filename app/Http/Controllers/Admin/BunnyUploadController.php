<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\DispatchTransferToBunny;
use App\Models\BunnyUpload;
use App\Services\BunnyStreamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;
use Pion\Laravel\ChunkUpload\Handler\HandlerFactory;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;

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
    /**
     * Dossier (relatif au disque PUBLIC) où vit le fichier assemblé.
     * Stocké sur le disque public ⇒ immédiatement lisible en local (fallback
     * automatique) tant que Bunny n'a pas pris le relais. Un seul fichier, pas
     * de double copie. Supprimé en arrière-plan une fois la vidéo prête côté Bunny.
     */
    private const UPLOAD_DIR = 'uploads';

    /** Extensions vidéo acceptées. */
    private const ALLOWED_EXT = ['mp4', 'mkv', 'mov', 'webm', 'avi', 'm4v', 'ts'];

    public function __construct(protected BunnyStreamService $bunny)
    {
    }

    /** Page d'upload + liste des uploads récents. */
    public function index(): View
    {
        $uploads = BunnyUpload::latest()->limit(50)->get();

        return view('admin.bunny.uploads', [
            'uploads'    => $uploads,
            'configured' => $this->bunny->isConfigured(),
        ]);
    }

    /**
     * JSON des uploads à afficher au chargement : ceux encore en cours, plus les
     * échecs dont l'original local est encore là (récupérer / relancer).
     */
    public function active(): JsonResponse
    {
        $uploads = BunnyUpload::query()
            ->where(function ($q) {
                $q->whereNotIn('status', BunnyUpload::TERMINAL)
                  ->orWhere(function ($q2) {
                      $q2->where('status', 'failed')->whereNotNull('temp_path');
                  });
            })
            ->latest()
            ->get()
            ->map(fn (BunnyUpload $u) => $this->present($u));

        return response()->json(['data' => $uploads]);
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

        $title = trim((string) ($data['title'] ?? '')) !== ''
            ? $data['title']
            : pathinfo($data['filename'], PATHINFO_FILENAME);

        $upload = BunnyUpload::create([
            'user_id'              => Auth::id(),
            'original_filename'    => $data['filename'],
            'title'                => $title,
            'size_bytes'           => $data['size'],
            'resumable_identifier' => $data['identifier'] ?? null,
            'status'               => 'uploading',
            'progress'             => 0,
        ]);

        return response()->json(['upload_id' => $upload->id]);
    }

    /**
     * Réception d'un chunk (POST) ou test de présence (GET, resumable.js).
     * Sur le dernier chunk, déclenche la finalisation + le Job de transfert.
     */
    public function chunk(Request $request): JsonResponse
    {
        // Requête de test resumable.js : on demande toujours l'envoi du chunk.
        if ($request->isMethod('get')) {
            return response()->json(['status' => 'upload'], 204);
        }

        $upload = BunnyUpload::find($request->input('upload_id'));
        if (! $upload) {
            return response()->json(['error' => 'Upload introuvable.'], 404);
        }
        if ($upload->isTerminal()) {
            return response()->json(['error' => 'Upload déjà terminé.'], 409);
        }

        $receiver = new FileReceiver('file', $request, HandlerFactory::classFromRequest($request));

        if ($receiver->isUploaded() === false) {
            throw new UploadMissingFileException();
        }

        $save = $receiver->receive();

        if ($save->isFinished()) {
            return $this->finalize($upload, $save->getFile());
        }

        // Chunk intermédiaire : progression de la réception nav→serveur.
        $handler = $save->handler();
        $percent = (int) $handler->getPercentageDone();

        $upload->update([
            'bytes_received' => (int) round($upload->size_bytes * $percent / 100),
            'progress'       => $percent,
        ]);

        return response()->json([
            'status'   => 'uploading',
            'done'     => $percent,
            'uploadId' => $upload->id,
        ]);
    }

    /** Statut JSON d'un upload (polling UI). */
    public function status(BunnyUpload $upload): JsonResponse
    {
        return response()->json($this->present($upload));
    }

    /**
     * Télécharge l'original conservé en local (filet de secours si Bunny échoue).
     * Disponible tant que le fichier temporaire existe (uploads non transférés/échoués).
     */
    public function download(BunnyUpload $upload): mixed
    {
        if (! $upload->temp_path || ! is_file($upload->temp_path)) {
            abort(404, 'Fichier original non disponible (déjà transféré vers Bunny ou supprimé).');
        }

        return response()->download($upload->temp_path, $upload->original_filename);
    }

    /**
     * Relance le transfert vers Bunny d'un upload échoué (après correction de la clé API).
     * Possible uniquement si l'original local est encore présent.
     */
    public function retry(BunnyUpload $upload): JsonResponse
    {
        if ($upload->status !== 'failed') {
            return response()->json(['error' => 'Seuls les uploads en échec peuvent être relancés.'], 422);
        }
        if (! $upload->temp_path || ! is_file($upload->temp_path)) {
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

    /* ---------------------------------------------------------------
     |  Helpers
     * --------------------------------------------------------------- */

    /**
     * Fichier complet assemblé : on le déplace sur le disque PUBLIC (lisible
     * immédiatement en local) et on enclenche le transfert autonome vers Bunny.
     * Le même fichier sert à la fois au fallback local et au PUT vers Bunny.
     */
    private function finalize(BunnyUpload $upload, UploadedFile $file): JsonResponse
    {
        $ext  = strtolower(pathinfo($upload->original_filename, PATHINFO_EXTENSION)) ?: 'mp4';
        $name = $upload->id . '_' . Str::random(8) . '.' . $ext;

        // Déplacement (et non copie) du fichier fusionné vers storage/app/public/uploads.
        $destDir      = Storage::disk('public')->path(self::UPLOAD_DIR);
        $relativePath = self::UPLOAD_DIR . '/' . $name;          // pour servir : asset('storage/'.$relativePath)
        $absolutePath = $destDir . DIRECTORY_SEPARATOR . $name;  // pour le Job (PUT vers Bunny)

        if (! is_dir($destDir)) {
            @mkdir($destDir, 0775, true);
        }
        $file->move($destDir, $name);

        $upload->update([
            'temp_path'      => $absolutePath,
            'local_path'     => $relativePath,
            'bytes_received' => $upload->size_bytes,
            'progress'       => 100,
            'status'         => 'queued',
            'uploaded_at'    => now(),
        ]);

        // La vidéo est dès maintenant attribuable (local) dans le picker.
        Cache::forget('bunny.videos.all');

        DispatchTransferToBunny::dispatch($upload->id);

        return response()->json([
            'status'   => 'queued',
            'done'     => 100,
            'uploadId' => $upload->id,
        ]);
    }

    private function present(BunnyUpload $upload): array
    {
        $hasLocalFile = (bool) ($upload->temp_path && is_file($upload->temp_path));

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
