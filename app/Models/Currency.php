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

    /**
     * Taux `rate_from_xof` d'un code devise (unités de la devise pour 1 XOF),
     * ou `$default` si la devise est absente. Utilisé par les services de
     * paiement pour convertir un montant de base (XOF) vers la devise débitée.
     */
    public static function rateFromXof(string $code, ?float $default = null): ?float
    {
        $currency = static::where('code', strtoupper($code))->first();

        return $currency ? (float) $currency->rate_from_xof : $default;
    }

    /**
     * Convertit un montant XOF vers un code devise (arrondi aux décimales de
     * la devise). Retourne null si la devise est inconnue.
     */
    public static function convertFromXofTo(float $amountXof, string $code): ?float
    {
        return static::where('code', strtoupper($code))->first()
            ?->convertFromXof($amountXof);
    }
}
