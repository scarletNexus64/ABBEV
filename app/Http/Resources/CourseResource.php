<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesMediaUrls;
use App\Models\CourseLesson;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Cours de cinéma. Sur la fiche, chaque leçon dit si l'utilisateur peut
 * l'ouvrir (`is_accessible`) : l'app affiche un cadenas plutôt que de
 * laisser l'utilisateur découvrir le refus après un tap.
 */
class CourseResource extends JsonResource
{
    use ResolvesMediaUrls;

    private bool $detailed = false;

    private ?User $viewer = null;

    public function detailed(?User $viewer): static
    {
        $this->detailed = true;
        $this->viewer = $viewer;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $lessons = $this->relationLoaded('lessons') ? $this->lessons : collect();

        $base = [
            'id' => (int) $this->id,
            'slug' => $this->slug,
            'title' => $this->t('title'),
            'type' => $this->type,
            'discipline' => $this->discipline,
            'level' => $this->level,
            'instructor_name' => $this->instructor_name,
            'instructor_title' => $this->instructor_title,
            'summary' => $this->t('summary'),
            'cover_url' => $this->absoluteUrl($this->cover_path),
            'required_tier' => $this->required_tier,
            'lessons_count' => (int) ($this->lessons_count ?? $lessons->count()),
            'total_minutes' => (int) $lessons->sum('duration_minutes'),
            'total_pages' => (int) $lessons->sum('pages'),
        ];

        if (! $this->detailed) {
            return $base;
        }

        $unlocked = $this->isUnlockedFor($this->viewer);

        return $base + [
            'description' => $this->t('description'),
            'is_unlocked' => $unlocked,
            'lessons' => $lessons->map(fn (CourseLesson $lesson) => [
                'id' => (int) $lesson->id,
                'title' => $lesson->t('title'),
                'summary' => $lesson->t('summary'),
                'position' => (int) $lesson->sort_order,
                'is_preview' => (bool) $lesson->is_preview,
                // Aperçu : tout compte connecté ; sinon le forfait du cours.
                'is_accessible' => $lesson->hasContent()
                    && ($unlocked || ($lesson->is_preview && $this->viewer !== null)),
                'has_content' => $lesson->hasContent(),
                'duration_minutes' => $lesson->duration_minutes,
                'pages' => $lesson->pages,
            ])->values(),
        ];
    }
}
