<?php

namespace App\Casts;

use App\Support\BusinessTime;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Horaire d'événement saisi en heure locale (séance, clôture d'un vote…).
 *
 *  - écriture : une chaîne SANS fuseau (« 2026-10-01T20:00 », saisie admin)
 *    est une heure du fuseau métier ; un objet date garde son instant ;
 *  - stockage : toujours en UTC, comme le reste de la base ;
 *  - lecture  : Carbon dans le fuseau métier — l'admin relit « 20:00 » et
 *    l'API émet « 2026-10-01T20:00:00+01:00 », que le téléphone affiche
 *    dans son propre fuseau.
 *
 * Sans ce cast, « 20:00 » saisi à Douala était stocké comme 20:00 UTC et
 * s'affichait 21:00 sur les téléphones du Cameroun.
 *
 * @implements CastsAttributes<Carbon, mixed>
 */
class BusinessDateTime implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value, 'UTC')->setTimezone(BusinessTime::zone());
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = $value instanceof DateTimeInterface
            ? Carbon::instance($value)
            : Carbon::parse((string) $value, BusinessTime::zone());

        return $date->utc()->format('Y-m-d H:i:s');
    }
}
