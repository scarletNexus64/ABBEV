<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Episode;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Panneau de modération (direction artistique / assistant) : visualise les
 * contenus en attente, les approuve (en confirmant catégorie + tier) ou les
 * rejette avec un motif. Réservé aux rôles admin et assistant.
 */
class ModerationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status', 'pending');
        if (! in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $status = 'pending';
        }

        $items = Media::with(['producer', 'category', 'reviewer'])
            ->where('moderation_status', $status)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $counts = [
            'pending'  => Media::where('moderation_status', 'pending')->count(),
            'approved' => Media::where('moderation_status', 'approved')->count(),
            'rejected' => Media::where('moderation_status', 'rejected')->count(),
        ];

        return view('moderation.index', compact('items', 'status', 'counts'));
    }

    public function show(Media $medium)
    {
        $medium->load(['producer', 'category', 'reviewer', 'seasonsRelation.episodes']);
        $categories = Category::orderBy('name')->get();

        // Source lisible du film (iframe Bunny ou fichier local signé) prête à
        // être lancée directement dans la page d'examen.
        $preview = $medium->isMovie() ? $this->playableSource($medium) : null;

        // Pour une série : une source par épisode.
        $episodePreviews = [];
        if ($medium->isSeries()) {
            foreach ($medium->seasonsRelation as $season) {
                foreach ($season->episodes as $ep) {
                    $episodePreviews[$ep->id] = $this->playableSource($ep);
                }
            }
        }

        return view('moderation.show', [
            'media' => $medium,
            'categories' => $categories,
            'tiers' => Media::TIERS,
            'preview' => $preview,
            'episodePreviews' => $episodePreviews,
        ]);
    }

    /**
     * Résout une source vidéo directement lisible pour la page d'examen :
     *  - Bunny  → URL d'embed iframe (lecteur Bunny) ;
     *  - locale → URL SIGNÉE à durée de vie courte vers le fichier (balise
     *    <video> native, seek/Range supporté).
     * Retourne ['kind' => 'bunny'|'local'|null, 'url' => ?string, 'mime' => ?string].
     */
    private function playableSource(Media|Episode $model): array
    {
        // Film Bunny : iframe.
        if ($model instanceof Media) {
            $embed = $model->bunnyEmbedUrl();
            if ($embed) {
                return ['kind' => 'bunny', 'url' => $embed, 'mime' => null];
            }
        } elseif ($model->video_provider === 'bunny' && $model->video_id) {
            $libraryId = $model->video_library_id ?: config('services.bunny.library_id');
            if ($libraryId) {
                return [
                    'kind' => 'bunny',
                    'url' => "https://iframe.mediadelivery.net/embed/{$libraryId}/{$model->video_id}",
                    'mime' => null,
                ];
            }
        }

        // Vidéo locale : URL signée vers le fichier si celui-ci existe réellement.
        if ($model->video_provider === 'local' && $model->video_path
            && Storage::disk('local')->exists($model->video_path)) {
            $type = $model instanceof Episode ? 'episode' : 'movie';
            $ext  = strtolower(pathinfo($model->video_path, PATHINFO_EXTENSION));
            $mime = [
                'mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'webm' => 'video/webm',
                'mov' => 'video/quicktime', 'mkv' => 'video/x-matroska',
            ][$ext] ?? 'video/mp4';

            $url = URL::temporarySignedRoute('api.watch.local', now()->addHours(2), [
                'type' => $type,
                'id' => $model->getKey(),
            ]);

            return ['kind' => 'local', 'url' => $url, 'mime' => $mime];
        }

        return ['kind' => null, 'url' => null, 'mime' => null];
    }

    public function approve(Request $request, Media $medium)
    {
        $data = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'tier' => 'required|in:classique,standard,premium',
        ]);

        $medium->update([
            'category_id' => $data['category_id'],
            'tier' => $data['tier'],
            'moderation_status' => 'approved',
            'rejection_reason' => null,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            // Publie immédiatement si aucune date de publication n'est fixée.
            'published_at' => $medium->published_at ?? now(),
        ]);

        return redirect()->route('moderation.index')
            ->with('success', "« {$medium->title} » approuvé et publié.");
    }

    public function reject(Request $request, Media $medium)
    {
        $data = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $medium->update([
            'moderation_status' => 'rejected',
            'rejection_reason' => $data['rejection_reason'],
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return redirect()->route('moderation.index')
            ->with('success', "« {$medium->title} » rejeté.");
    }
}
