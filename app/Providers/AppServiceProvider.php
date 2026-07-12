<?php

namespace App\Providers;

use App\Support\RuntimeBunnyConfig;
use App\Support\RuntimeExchangeRateConfig;
use App\Support\RuntimeMailConfig;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Applique les configs pilotées depuis le dashboard (groupes « email »,
        // « bunny », « exchange_rate ») par-dessus le .env. Résilient si la table
        // n'existe pas encore.
        RuntimeMailConfig::apply();
        RuntimeBunnyConfig::apply();
        RuntimeExchangeRateConfig::apply();
    }
}
