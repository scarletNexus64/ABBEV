<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Rôle (ou poste technique) à pourvoir dans une annonce de casting. */
class CastingRole extends Model
{
    use HasTranslations;

    public array $translatable = ['description'];

    public const IMPORTANCE = [
        'principal' => 'Rôle principal',
        'secondaire' => 'Second rôle',
        'figuration' => 'Figuration',
    ];

    public const GENDERS = [
        'indifferent' => 'Indifférent',
        'femme' => 'Femme',
        'homme' => 'Homme',
    ];

    protected $fillable = [
        'casting_call_id', 'name', 'kind', 'profession', 'importance', 'gender',
        'age_min', 'age_max', 'min_tier', 'description', 'requirements',
        'positions', 'sort_order',
    ];

    protected $casts = [
        'age_min' => 'integer',
        'age_max' => 'integer',
        'positions' => 'integer',
        'sort_order' => 'integer',
    ];

    public function call(): BelongsTo
    {
        return $this->belongsTo(CastingCall::class, 'casting_call_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(CastingApplication::class);
    }

    /** « 25 – 35 ans », « 18 ans et + », ou null si aucun âge n'est exigé. */
    public function ageRangeLabel(): ?string
    {
        return match (true) {
            $this->age_min && $this->age_max => "{$this->age_min} – {$this->age_max} ans",
            (bool) $this->age_min => "{$this->age_min} ans et +",
            (bool) $this->age_max => "jusqu'à {$this->age_max} ans",
            default => null,
        };
    }
}
