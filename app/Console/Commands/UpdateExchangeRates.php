<?php

namespace App\Console\Commands;

use App\Services\ExchangeRateService;
use Illuminate\Console\Command;

/**
 * Met à jour les taux de change (currencies.rate_from_xof) depuis ExchangeRate-API.
 * Planifiée quotidiennement (routes/console.php) ; lançable manuellement :
 *
 *   php artisan rates:update
 */
class UpdateExchangeRates extends Command
{
    protected $signature = 'rates:update';

    protected $description = 'Met à jour les taux de change des devises depuis ExchangeRate-API';

    public function handle(ExchangeRateService $service): int
    {
        if (! $service->isConfigured()) {
            $this->error('EXCHANGERATE_API_KEY absente : configurez la clé puis relancez.');

            return self::FAILURE;
        }

        try {
            $count = $service->updateCurrencies();
            $this->info("{$count} devise(s) mise(s) à jour avec les taux live.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Échec de la mise à jour des taux : ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
