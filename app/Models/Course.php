<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Concerns\HasObfuscatedRouteKey;
use App\Models\Concerns\HasSlug;
use App\Models\Concerns\HasTranslations;
use App\Support\TierAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cours de cinéma : parcours en VIDÉOS ou en DOCUMENTS (les deux
 * sous-catégories de cat.md), découpé en leçons.
 */
class Course extends Model
{
    use BelongsToWorkspace, HasObfuscatedRouteKey, HasSlug, HasTranslations;

    public array $translatable = ['title', 'summary', 'description'];

    public const TYPES = [
        'video' => 'Vidéos',
        'document' => 'Documents',
    ];

    public const DISCIPLINES = [
        'realisation' => 'Réalisation',
        'scenario' => 'Scénario',
        'jeu' => "Jeu d'acteur",
        'image' => 'Image & lumière',
        'son' => 'Son',
        'montage' => 'Montage',
        'production' => 'Production',
        'decors-costumes' => 'Décors & costumes',
        'autre' => 'Autre',
    ];

    public const LEVELS = [
        'debutant' => 'Débutant',
        'intermediaire' => 'Intermédiaire',
        'avance' => 'Avancé',
    ];

    protected $fillable = [
        'title', 'slug', 'type', 'discipline', 'level', 'instructor_name',
        'instructor_title', 'summary', 'description', 'cover_path',
        'required_tier', 'is_published', 'sort_order',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected function slugSource(): string
    {
        return (string) $this->title;
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(CourseLesson::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /** L'utilisateur peut-il suivre l'intégralité du cours ? */
    public function isUnlockedFor(?User $user): bool
    {
        return $user !== null && TierAccess::allows($user, $this->required_tier);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function disciplineLabel(): string
    {
        return self::DISCIPLINES[$this->discipline] ?? $this->discipline;
    }

    public function levelLabel(): string
    {
        return self::LEVELS[$this->level] ?? $this->level;
    }

    /** Durée totale des leçons vidéo, en minutes. */
    public function totalMinutes(): int
    {
        return (int) $this->lessons->sum('duration_minutes');
    }

    /** Nombre total de pages des leçons en PDF. */
    public function totalPages(): int
    {
        return (int) $this->lessons->sum('pages');
    }
}
