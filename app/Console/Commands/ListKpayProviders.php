<?php

namespace App\Console\Commands;

use App\Services\KpayService;
use Illuminate\Console\Command;

/**
 * Liste les providers réellement disponibles sur le compte KPay, et les
 * confronte aux codes déclarés dans config/kpay.php. Sert à VÉRIFIER que les
 * codes provider (MTN_MOMO_CMR, AIRTEL_COD, …) sont exacts avant d'encaisser.
 *
 *   php artisan kpay:providers
 */
class ListKpayProviders extends Command
{
    protected $signature = 'kpay:providers';

    protected $description = 'Liste les providers KPay et vérifie les codes de config/kpay.php';

    public function handle(KpayService $kpay): int
    {
        $result = $kpay->listProviders();

        if (! ($result['success'] ?? false)) {
            $this->error('Impossible de récupérer les providers KPay : ' . ($result['message'] ?? 'erreur inconnue'));
            $this->line('Vérifiez les credentials KPay dans le dashboard, ou l\'endpoint /api/v1/providers.');

            return self::FAILURE;
        }

        // On collecte tous les codes provider connus de KPay (formats variables
        // selon l'API : liste d'objets {code|id|name} ou map). On aplatit.
        $data = $result['data'] ?? [];
        $known = [];
        array_walk_recursive($data, function ($v) use (&$known) {
            if (is_string($v)) {
                $known[] = $v;
            }
        });
        $known = array_unique($known);

        $this->info('Providers renvoyés par KPay :');
        $this->line(implode(', ', $known) ?: '(aucun)');
        $this->newLine();

        // Confrontation avec config/kpay.php.
        $this->info('Vérification de config/kpay.php :');
        $countries = (array) config('kpay.countries', []);
        $missing = 0;
        foreach ($countries as $iso => $country) {
            foreach (($country['operators'] ?? []) as $op => $code) {
                $ok = in_array($code, $known, true);
                if (! $ok) {
                    $missing++;
                }
                $this->line(sprintf(
                    '  [%s] %-14s → %-18s %s',
                    $iso,
                    $op,
                    $code,
                    $ok ? '<info>OK</info>' : '<comment>ABSENT chez KPay ?</comment>'
                ));
            }
        }

        $this->newLine();
        if ($missing > 0) {
            $this->warn("{$missing} code(s) provider ne correspondent pas à la liste KPay — corrigez config/kpay.php.");
        } else {
            $this->info('Tous les codes de config/kpay.php correspondent à KPay.');
        }

        return self::SUCCESS;
    }
}
