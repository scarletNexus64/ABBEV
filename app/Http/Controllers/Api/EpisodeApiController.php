<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Models\Media;
use App\Models\Season;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EpisodeApiController extends Controller
{
    // --- Public ---
    public function seasonsOfMedia(Media $media): JsonResponse
    {
        return response()->json(
            $media->seasonsRelation()->with('episodes')->get()
        );
    }

    public function show(Episode $episode): JsonResponse
    {
        return response()->json($episode->load('season.media'));
    }

    // --- Admin : Saisons ---
    public function storeSeason(Request $request, Media $media): JsonResponse
    {
        $data = $request->validate([
            'season_number' => 'required|integer|min:1',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'release_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 5),
        ]);

        $season = $media->seasonsRelation()->create($data);

        return response()->json($season, 201);
    }

    public function destroySeason(Season $season): JsonResponse
    {
        foreach ($season->episodes as $ep) {
            foreach (['video_path', 'thumbnail_path'] as $k) {
                if ($ep->$k) {
                    Storage::disk('public')->delete($ep->$k);
                }
            }
        }
        $season->delete();

        return response()->json(['message' => 'Saison supprimée.']);
    }

    // --- Admin : Épisodes ---
    public function storeEpisode(Request $request, Season $season): JsonResponse
    {
        $data = $request->validate([
            'episode_number' => 'required|integer|min:1',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'duration' => 'nullable|integer|min:1',
            'video_path' => 'required|string',
            'thumbnail_path' => 'nullable|string',
            'published_at' => 'nullable|date',
        ]);

        $episode = $season->episodes()->create($data);
        $season->updateEpisodesCount();

        return response()->json($episode, 201);
    }

    public function update(Request $request, Episode $episode): JsonResponse
    {
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'duration' => 'nullable|integer|min:1',
            'video_path' => 'nullable|string',
            'thumbnail_path' => 'nullable|string',
            'published_at' => 'nullable|date',
        ]);

        if (isset($data['video_path']) && $episode->video_path && $data['video_path'] !== $episode->video_path) {
            Storage::disk('public')->delete($episode->video_path);
        }

        $episode->update($data);

        return response()->json($episode->fresh());
    }

    public function destroy(Episode $episode): JsonResponse
    {
        foreach (['video_path', 'thumbnail_path'] as $k) {
            if ($episode->$k) {
                Storage::disk('public')->delete($episode->$k);
            }
        }
        $season = $episode->season;
        $episode->delete();
        $season?->updateEpisodesCount();

        return response()->json(['message' => 'Épisode supprimé.']);
    }
}
