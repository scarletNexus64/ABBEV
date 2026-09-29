<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Concerns\HasObfuscatedRouteKey;
use App\Models\Concerns\HasSlug;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Acteur, actrice ou technicien de l'annuaire ABBEV.
 *
 * Le RANG reprend la grille de cat.md, commune aux comédiens et aux
 * techniciens :
 *   A² — artiste icône : une légende dans son corps de métier ;
 *   A¹ — star : plusieurs projets à succès sur la durée, toujours actif ;
 *   B  — vedette : un ou plusieurs projets spontanés à succès ;
 *   C  — confirmé : professionnel en activité ;
 *   D  — amateur : fraîchement sorti d'école ou de formation en cinéma.
 */
class Talent extends Model
{
    use BelongsToWorkspace, HasObfuscatedRouteKey, HasSlug, HasTranslations;

    /** Explicite : l'inflecteur anglais tient « talent » pour indénombrable. */
    protected $table = 'talents';

    public array $translatable = ['headline', 'bio'];

    public const KINDS = [
        'acteur' => 'Acteur / actrice',
        'technicien' => 'Technicien / technicienne',
    ];

    /** Rangs, du plus au moins établi (l'ordre sert aux tris). */
    public const TIERS = ['A2', 'A1', 'B', 'C', 'D'];

    public const TIER_LABELS = [
        'A2' => 'A² — Icône',
        'A1' => 'A¹ — Star',
        'B' => 'B — Vedette',
        'C' => 'C — Confirmé',
        'D' => 'D — Amateur',
    ];

    public const TIER_DESCRIPTIONS = [
        'A2' => 'Artiste icône : une légende dans son corps de métier.',
        'A1' => 'Plusieurs projets à succès sur la durée, toujours actif.',
        'B' => 'Un ou plusieurs projets spontanés à succès.',
        'C' => 'Professionnel confirmé, en activité.',
        'D' => "Fraîchement sorti d'école ou de formation en cinéma.",
    ];

    /** Métiers de l'annuaire : les comédiens, puis les postes techniques. */
    public const PROFESSIONS = [
        'acteur' => 'Comédien(ne)',
        'realisateur' => 'Réalisation',
        'assistant-realisateur' => 'Assistanat réalisation',
        'scenariste' => 'Scénario',
        'producteur' => 'Production',
        'directeur-photo' => 'Direction de la photographie',
        'cadreur' => 'Cadre',
        'ingenieur-son' => 'Son',
        'monteur' => 'Montage',
        'compositeur' => 'Musique',
        'chef-decorateur' => 'Décors',
        'costumier' => 'Costumes',
        'maquilleur' => 'Maquillage & coiffure',
        'effets-speciaux' => 'Effets spéciaux',
        'scripte' => 'Scripte',
        'regisseur' => 'Régie',
        'directeur-casting' => 'Direction de casting',
        'autre' => 'Autre métier',
    ];

    public const GENDERS = [
        'femme' => 'Femme',
        'homme' => 'Homme',
    ];

    protected $fillable = [
        'kind', 'first_name', 'last_name', 'stage_name', 'slug', 'tier',
        'profession', 'gender', 'birth_year', 'country_code', 'city',
        'headline', 'bio', 'photo_path', 'playing_age_min', 'playing_age_max',
        'height_cm', 'languages', 'skills', 'showreel_url', 'agent_id',
        'is_featured', 'is_published',
    ];

    protected $casts = [
        'languages' => 'array',
        'skills' => 'array',
        'is_featured' => 'boolean',
        'is_published' => 'boolean',
        'birth_year' => 'integer',
        'playing_age_min' => 'integer',
        'playing_age_max' => 'integer',
        'height_cm' => 'integer',
    ];

    protected function slugSource(): string
    {
        return $this->displayName();
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function credits(): HasMany
    {
        return $this->hasMany(TalentCredit::class)->orderBy('sort_order')->orderByDesc('year');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * Tri « annuaire » : les rangs les plus établis d'abord (A² → D), puis
     * l'ordre alphabétique. Écrit en CASE pour rester portable (PostgreSQL en
     * production, SQLite en test).
     */
    public function scopeOrderByTier(Builder $query): Builder
    {
        $case = 'CASE tier';
        foreach (self::TIERS as $i => $tier) {
            $case .= " WHEN '{$tier}' THEN {$i}";
        }
        $case .= ' ELSE 99 END';

        return $query->orderByRaw($case)->orderBy('last_name')->orderBy('first_name');
    }

    /** Nom affiché : le nom de scène s'il existe, sinon prénom + nom. */
    public function displayName(): string
    {
        return $this->stage_name ?: trim($this->first_name . ' ' . $this->last_name);
    }

    public function tierLabel(): string
    {
        return self::TIER_LABELS[$this->tier] ?? $this->tier;
    }

    public function professionLabel(): string
    {
        return self::PROFESSIONS[$this->profession] ?? ucfirst((string) $this->profession);
    }

    public function age(): ?int
    {
        return $this->birth_year ? max(0, (int) now()->year - $this->birth_year) : null;
    }
}
