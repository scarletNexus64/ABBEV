<?php

namespace App\Support;

use App\Models\Currency;

/**
 * Affichage des montants en devise locale (FCFA, ₦, €…), sans conversion.
 *
 * Même règle que la billetterie : un montant saisi en XAF reste en XAF ; on
 * n'enrichit que le symbole et le nombre de décimales d'affichage.
 */
class Money
{
    /** @var array<string, array{symbol: string, decimals: int}> */
    private static array $cache = [];

    /** @return array{symbol: string, decimals: int} */
    public static function meta(?string $code): array
    {
        $code = strtoupper((string) $code);
        if ($code === '') {
            return ['symbol' => '', 'decimals' => 0];
        }

        return self::$cache[$code] ??= (function () use ($code) {
            $currency = Currency::where('code', $code)->first();

            return [
                'symbol' => $currency?->symbol ?: $code,
                'decimals' => (int) ($currency?->decimals ?? 0),
            ];
        })();
    }

    /** « 1 500 000 FCFA » — espaces insécables comme séparateur de milliers. */
    public static function format(float|int|string|null $amount, ?string $code): string
    {
        $meta = self::meta($code);

        return number_format((float) $amount, $meta['decimals'], ',', "\u{202F}") . ' ' . $meta['symbol'];
    }
}
