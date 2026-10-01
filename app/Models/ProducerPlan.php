<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Pack producteur : ce que paie un producteur pour ouvrir son espace du
 * panel (upload de films, modules…). Il n'y en a qu'UN, configuré par l'admin
 * (prix, période mensuelle ou annuelle).
 *
 * Tant qu'aucun pack actif n'existe, les espaces producteurs restent ouverts :
 * désactiver le pack lève donc le verrou pour tout le monde.
 */
class ProducerPlan extends Model
{
    public const PERIODS = [
        'month' => 'Mois',
        'year'  => 'Année',
    ];

    protected $fillable = [
        'name', 'description', 'price', 'billing_period', 'period_count', 'features', 'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'period_count' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    /** Le pack unique (le plus ancien si, par accident, il y en avait plusieurs). */
    public static function current(): ?self
    {
        return static::query()->oldest('id')->first();
    }

    /** Le paiement est-il exigé des producteurs ? */
    public static function paymentRequired(): bool
    {
        return (bool) static::current()?->is_active;
    }

    /** Fin d'une période démarrant à $start. */
    public function periodEndFrom(CarbonInterface $start): CarbonInterface
    {
        return self::addPeriod($start, $this->billing_period, $this->period_count);
    }

    public static function addPeriod(CarbonInterface $start, string $period, int $count): CarbonInterface
    {
        $count = max(1, $count);

        return $period === 'year'
            ? $start->copy()->addYearsNoOverflow($count)
            : $start->copy()->addMonthsNoOverflow($count);
    }

    /** « par mois », « tous les 3 mois », « par an », « tous les 2 ans ». */
    public function periodLabel(): string
    {
        $count = max(1, (int) $this->period_count);

        if ($this->billing_period === 'year') {
            return $count === 1 ? 'par an' : "tous les {$count} ans";
        }

        return $count === 1 ? 'par mois' : "tous les {$count} mois";
    }

    /** « 1 mois », « 3 mois », « 1 an », « 2 ans ». */
    public function durationLabel(): string
    {
        $count = max(1, (int) $this->period_count);

        if ($this->billing_period === 'year') {
            return $count === 1 ? '1 an' : "{$count} ans";
        }

        return "{$count} mois";
    }

    public function subscriptions()
    {
        return $this->hasMany(ProducerSubscription::class);
    }
}
