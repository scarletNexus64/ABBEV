<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * CRUD admin sur les médias depuis l'API (consommé par Flutter / outils admin).
 * Le fichier vidéo n'est PAS envoyé en multipart ici : il est uploadé en amont
 * via /api/v1/admin/upload/chunk, et le chemin renvoyé est passé en `video_path`.
 */
class AdminMediaApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Media::with('category')->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('title', 'like', "%{$s}%")->orWhere('description', 'like', "%{$s}%"));
        }

        return response()->json($query->paginate($request->get('per_page', 20)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|in:movie,series',
            'category_id' => 'required|exists:categories,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'duration' => 'nullable|integer|min:1', // minutes
            'release_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 5),
            'seasons' => 'nullable|integer|min:1',
            'video_path' => 'nullable|string',
            'thumbnail_path' => 'nullable|string',
            'cover_path' => 'nullable|string',
            'banner_path' => 'nullable|string',
            'is_featured' => 'nullable|boolean',
            'published_at' => 'nullable|date',
        ]);

        $data['slug'] = Str::slug($data['title']);
        if (isset($data['duration'])) {
            $data['duration'] = $data['duration'] * 60;
        }
        $data['is_featured'] = (bool) ($data['is_featured'] ?? false);

        $media = Media::create($data);

        return response()->json($media->load('category'), 201);
    }

    public function show(Media $media): JsonResponse
    {
        return response()->json($media->load(['category', 'seasonsRelation.episodes']));
    }

    public function update(Request $request, Media $media): JsonResponse
    {
        $data = $request->validate([
            'type' => 'sometimes|in:movie,series',
            'category_id' => 'sometimes|exists:categories,id',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'duration' => 'nullable|integer|min:1',
            'release_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 5),
            'seasons' => 'nullable|integer|min:1',
            'video_path' => 'nullable|string',
            'thumbnail_path' => 'nullable|string',
            'cover_path' => 'nullable|string',
            'banner_path' => 'nullable|string',
            'is_featured' => 'nullable|boolean',
            'published_at' => 'nullable|date',
        ]);

        if (isset($data['title'])) {
            $data['slug'] = Str::slug($data['title']);
        }
        if (isset($data['duration'])) {
            $data['duration'] = $data['duration'] * 60;
        }

        // Si on remplace une vidéo, supprimer l'ancienne du disque
        if (isset($data['video_path']) && $data['video_path'] !== $media->video_path && $media->video_path) {
            if (! str_starts_with($media->video_path, 'http')) {
                Storage::disk('public')->delete($media->video_path);
            }
        }

        $media->update($data);

        return response()->json($media->fresh()->load('category'));
    }

    public function destroy(Media $media): JsonResponse
    {
        foreach (['video_path', 'thumbnail_path', 'cover_path', 'banner_path'] as $k) {
            if ($media->$k && ! str_starts_with($media->$k, 'http')) {
                Storage::disk('public')->delete($media->$k);
            }
        }
        $media->delete();

        return response()->json(['message' => 'Média supprimé.']);
    }
}
