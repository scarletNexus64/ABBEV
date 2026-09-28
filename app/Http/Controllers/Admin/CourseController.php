<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesUploads;
use App\Http\Controllers\Admin\Concerns\SavesTranslations;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Support\TierAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Cours de cinéma (cat.md : documents et vidéos) et leurs leçons.
 *
 * Une leçon vidéo pointe soit sur Bunny Stream (identifiant de la vidéo,
 * comme les films), soit sur un lien direct MP4/HLS ; une leçon document est
 * un PDF stocké sur le disque privé, lu dans l'app sans téléchargement.
 */
class CourseController extends Controller
{
    use HandlesUploads, SavesTranslations;

    private const TRANSLATABLE = ['title', 'summary', 'description'];

    public function index(Request $request)
    {
        $type = $request->query('type');

        $courses = Course::withCount('lessons')
            ->withSum('lessons', 'duration_minutes')
            ->withSum('lessons', 'pages')
            ->when(array_key_exists((string) $type, Course::TYPES), fn ($q) => $q->where('type', $type))
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        return view('courses.index', [
            'courses' => $courses,
            'type' => $type,
            'stats' => [
                'video' => Course::where('type', 'video')->count(),
                'document' => Course::where('type', 'document')->count(),
                'lessons' => CourseLesson::count(),
                'published' => Course::where('is_published', true)->count(),
            ],
        ]);
    }

    public function create(Request $request)
    {
        $type = $request->query('type') === 'document' ? 'document' : 'video';

        return view('courses.create', [
            'course' => new Course(['type' => $type, 'discipline' => 'realisation', 'level' => 'debutant']),
            'tiers' => TierAccess::LABELS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $course = Course::create($this->attributes($request, $data, new Course()) + [
            'sort_order' => (int) Course::max('sort_order') + 1,
        ]);
        $this->saveEnglish($course, $request, self::TRANSLATABLE);

        return redirect()->route('courses.edit', $course)
            ->with('success', 'Cours créé. Ajoutez maintenant ses leçons.');
    }

    public function edit(Course $course)
    {
        $course->load(['lessons', 'translations']);

        return view('courses.edit', ['course' => $course, 'tiers' => TierAccess::LABELS]);
    }

    public function update(Request $request, Course $course)
    {
        $data = $this->validated($request, $course);
        $course->update($this->attributes($request, $data, $course));
        $this->saveEnglish($course, $request, self::TRANSLATABLE);

        return back()->with('success', 'Cours enregistré.');
    }

    public function destroy(Course $course)
    {
        foreach ($course->lessons as $lesson) {
            $this->forgetLessonFile($lesson);
        }
        $this->forgetPublic($course->cover_path);
        $course->translations()->delete();
        $course->delete();

        return redirect()->route('courses.index')->with('success', 'Cours supprimé.');
    }

    // ------------------------------------------------------------------
    //  Leçons
    // ------------------------------------------------------------------

    public function storeLesson(Request $request, Course $course)
    {
        $data = $this->validatedLesson($request, $course, null);

        $lesson = new CourseLesson(['course_id' => $course->id]);
        $this->fillLesson($lesson, $request, $data, $course);
        $lesson->sort_order = (int) CourseLesson::where('course_id', $course->id)->max('sort_order') + 1;
        $lesson->save();

        return redirect()->route('courses.edit', $course)->withFragment('lecons')
            ->with('success', "Leçon « {$lesson->title} » ajoutée.");
    }

    public function updateLesson(Request $request, CourseLesson $lesson)
    {
        $course = $lesson->course;
        $data = $this->validatedLesson($request, $course, $lesson);

        $this->fillLesson($lesson, $request, $data, $course);
        $lesson->save();

        return redirect()->route('courses.edit', $course)->withFragment('lecon-' . $lesson->id)
            ->with('success', "Leçon « {$lesson->title} » enregistrée.");
    }

    public function destroyLesson(CourseLesson $lesson)
    {
        $course = $lesson->course;
        $this->forgetLessonFile($lesson);
        $lesson->translations()->delete();
        $lesson->delete();

        return redirect()->route('courses.edit', $course)->withFragment('lecons')
            ->with('success', 'Leçon supprimée.');
    }

    public function moveLesson(Request $request, CourseLesson $lesson)
    {
        $ids = CourseLesson::where('course_id', $lesson->course_id)
            ->orderBy('sort_order')->orderBy('id')->pluck('id')->values()->all();
        $i = array_search($lesson->id, $ids, true);
        $j = $request->input('direction') === 'up' ? $i - 1 : $i + 1;

        if ($i !== false && isset($ids[$j])) {
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
            DB::transaction(function () use ($ids) {
                foreach ($ids as $position => $id) {
                    CourseLesson::whereKey($id)->update(['sort_order' => $position + 1]);
                }
            });
        }

        return redirect()->route('courses.edit', $lesson->course_id)->withFragment('lecons');
    }

    /** PDF d'une leçon, pour relecture dans l'admin. */
    public function lessonFile(CourseLesson $lesson)
    {
        abort_unless($lesson->file_path && Storage::disk('local')->exists($lesson->file_path), 404);

        return response()->file(Storage::disk('local')->path($lesson->file_path), [
            'Content-Type' => 'application/pdf',
        ]);
    }

    // ------------------------------------------------------------------

    private function validated(Request $request, ?Course $course = null): array
    {
        return $request->validate([
            'title' => 'required|string|max:190',
            'type' => ['required', Rule::in(array_keys(Course::TYPES))],
            'discipline' => ['required', Rule::in(array_keys(Course::DISCIPLINES))],
            'level' => ['required', Rule::in(array_keys(Course::LEVELS))],
            'instructor_name' => 'nullable|string|max:120',
            'instructor_title' => 'nullable|string|max:160',
            'summary' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:6000',
            'required_tier' => ['nullable', Rule::in(TierAccess::TIERS)],
            'is_published' => 'required|boolean',
            'cover' => 'nullable|image|max:4096',
        ] + $this->translationRules(self::TRANSLATABLE));
    }

    private function attributes(Request $request, array $data, Course $course): array
    {
        return [
            'title' => $data['title'],
            'type' => $data['type'],
            'discipline' => $data['discipline'],
            'level' => $data['level'],
            'instructor_name' => $data['instructor_name'] ?? null,
            'instructor_title' => $data['instructor_title'] ?? null,
            'summary' => $data['summary'] ?? null,
            'description' => $data['description'] ?? null,
            'required_tier' => ($data['required_tier'] ?? null) ?: null,
            'is_published' => (bool) $data['is_published'],
            'cover_path' => $this->replaceImage($request, 'cover', 'courses', $course->cover_path),
        ];
    }

    private function validatedLesson(Request $request, Course $course, ?CourseLesson $lesson): array
    {
        $isVideo = $course->type === 'video';

        return $request->validate([
            'title' => 'required|string|max:190',
            'summary' => 'nullable|string|max:500',
            'is_preview' => 'required|boolean',
            // Vidéo : une source obligatoire (Bunny OU lien direct).
            'video_provider' => $isVideo ? ['required', Rule::in(['bunny', 'url'])] : 'nullable',
            'video_id' => $isVideo ? 'nullable|required_if:video_provider,bunny|string|max:128' : 'nullable',
            'video_url' => $isVideo ? 'nullable|required_if:video_provider,url|url|max:500' : 'nullable',
            'duration_minutes' => 'nullable|integer|min:1|max:600',
            // Document : un PDF, obligatoire à la création seulement.
            'file' => $isVideo ? 'nullable' : [$lesson?->file_path ? 'nullable' : 'required', 'file', 'mimes:pdf', 'max:30720'],
        ]);
    }

    private function fillLesson(CourseLesson $lesson, Request $request, array $data, Course $course): void
    {
        $lesson->title = $data['title'];
        $lesson->summary = $data['summary'] ?? null;
        $lesson->is_preview = (bool) $data['is_preview'];

        if ($course->type === 'video') {
            $lesson->video_provider = $data['video_provider'];
            $lesson->video_id = $data['video_provider'] === 'bunny' ? trim((string) $data['video_id']) : null;
            $lesson->video_url = $data['video_provider'] === 'url' ? $data['video_url'] : null;
            $lesson->duration_minutes = $data['duration_minutes'] ?? null;

            return;
        }

        if ($request->hasFile('file')) {
            $lesson->file_path = $this->replacePrivateFile($request, 'file', 'courses/lessons', $lesson->file_path);
            $lesson->pages = $this->countPdfPages(Storage::disk('local')->path($lesson->file_path));
        }
    }

    private function forgetLessonFile(CourseLesson $lesson): void
    {
        if ($lesson->file_path && ! str_starts_with($lesson->file_path, 'demo/')) {
            Storage::disk('local')->delete($lesson->file_path);
        }
    }

    /** Nombre de pages d'un PDF (comptage des objets /Page), ou null. */
    private function countPdfPages(string $absolutePath): ?int
    {
        try {
            preg_match_all('/\/Type\s*\/Page[^s]/', (string) file_get_contents($absolutePath), $m);

            return count($m[0]) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
