<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = [
        'code',
        'name',
        'symbol',
        'rate_from_xof',
        'decimals',
        'is_active',
    ];

    protected $casts = [
        'rate_from_xof' => 'decimal:8',
        'decimals' => 'integer',
        'is_active' => 'boolean',
    ];

    public function countries()
    {
        return $this->hasMany(Country::class, 'currency_code', 'code');
    }

    /**
     * Convertit un montant exprimé en XOF (FCFA) vers cette devise.
     * Arrondi au nombre de décimales défini pour la devise.
     */
    public function convertFromXof(float $amountXof): float
    {
        $converted = $amountXof * (float) $this->rate_from_xof;

        return round($converted, (int) $this->decimals);
    }
}
