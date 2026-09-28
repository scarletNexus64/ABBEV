<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\Configuration;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Services\BunnyStreamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Cours de cinéma : catalogue public, contenu des leçons après contrôle.
 *
 * Aucune URL de vidéo ou de PDF ne figure dans le catalogue : elles sont
 * délivrées une à une par `access()`, à durée de vie courte, comme pour les
 * films (cf. WatchApiController).
 */
class CourseApiController extends Controller
{
    /** GET /courses — filtres `type` (video|document), `discipline`, `level`. */
    public function index(Request $request): JsonResponse
    {
        $query = Course::published()
            ->with('lessons:id,course_id,duration_minutes,pages')
            ->withCount('lessons')
            ->orderBy('sort_order')
            ->orderByDesc('id');

        if (array_key_exists((string) $request->query('type'), Course::TYPES)) {
            $query->where('type', $request->query('type'));
        }
        if (array_key_exists((string) $request->query('discipline'), Course::DISCIPLINES)) {
            $query->where('discipline', $request->query('discipline'));
        }
        if (array_key_exists((string) $request->query('level'), Course::LEVELS)) {
            $query->where('level', $request->query('level'));
        }

        return response()->json([
            'data' => CourseResource::collection($query->get())->resolve($request),
        ]);
    }

    /** GET /courses/{course} — fiche et plan du cours. */
    public function show(Request $request, Course $course): JsonResponse
    {
        abort_unless($course->is_published, 404);

        $course->load('lessons')->loadCount('lessons');

        return response()->json([
            'data' => (new CourseResource($course))
                ->detailed($request->user('sanctum'))
                ->resolve($request),
        ]);
    }

    /**
     * GET /courses/{course}/lessons/{lesson}/access (compte requis)
     *
     * Document → URL signée du PDF (2 h) ; vidéo → lecteur Bunny ou lien
     * direct, selon la source.
     */
    public function access(Request $request, Course $course, CourseLesson $lesson): JsonResponse
    {
        abort_unless($course->is_published && $lesson->course_id === $course->id, 404);

        if (! $lesson->hasContent()) {
            return response()->json(['message' => __('messages.courses.no_content')], 404);
        }

        $user = $request->user();
        if (! $course->isUnlockedFor($user) && ! $lesson->is_preview) {
            return response()->json([
                'message' => __('messages.courses.locked'),
                'required_tier' => $course->required_tier,
            ], 403);
        }

        if (filled($lesson->file_path)) {
            return response()->json(['data' => [
                'type' => 'document',
                'title' => $lesson->t('title'),
                'pages' => $lesson->pages,
                'file_url' => URL::temporarySignedRoute(
                    'api.course-lessons.file',
                    now()->addHours(2),
                    ['lesson' => $lesson->id],
                ),
            ]]);
        }

        return response()->json(['data' => ['type' => 'video', 'title' => $lesson->t('title')] + $this->videoUrls($lesson)]);
    }

    /** GET /course-lessons/{lesson}/file (URL signée) — le PDF lui-même. */
    public function file(CourseLesson $lesson): BinaryFileResponse
    {
        $disk = Storage::disk('local');
        abort_unless($lesson->file_path && $disk->exists($lesson->file_path), 404);

        return response()->file($disk->path($lesson->file_path), [
            'Content-Type' => 'application/pdf',
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * Mêmes règles que la lecture des films : le mode « test » de la
     * configuration remplace les vidéos Bunny par un échantillon public.
     */
    private function videoUrls(CourseLesson $lesson): array
    {
        if ($lesson->video_provider === 'bunny' && $lesson->video_id) {
            if (Configuration::getValue('video_mode', 'production') === 'test') {
                return [
                    'video_url' => (string) Configuration::getValue(
                        'video_test_sample_hls',
                        'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8',
                    ),
                    'embed_url' => null,
                ];
            }

            $bunny = app(BunnyStreamService::class);
            if ($bunny->isConfigured()) {
                return [
                    'video_url' => $bunny->hlsUrl($lesson->video_id),
                    'embed_url' => $bunny->embedUrl($lesson->video_id),
                ];
            }
        }

        return ['video_url' => $lesson->video_url, 'embed_url' => null];
    }
}
