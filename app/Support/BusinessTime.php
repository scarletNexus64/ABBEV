<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Heure « métier » de la plateforme (Africa/Douala par défaut).
 *
 * L'application stocke et calcule en UTC, mais les horaires d'événements que
 * l'équipe saisit dans l'admin — une séance à 20h, la clôture d'un vote à
 * 23h59 — sont des heures LOCALES. Ce fuseau sert à les interpréter à la
 * saisie et à les restituer (admin, API) ; voir {@see \App\Casts\BusinessDateTime}.
 */
final class BusinessTime
{
    public static function zone(): string
    {
        return (string) config('app.business_timezone', 'Africa/Douala');
    }

    /** Maintenant, dans le fuseau métier (« aujourd'hui », « ce soir à 20h »…). */
    public static function now(): Carbon
    {
        return Carbon::now(self::zone());
    }
}
