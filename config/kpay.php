<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pays, opérateurs et codes provider KPay (Mobile Money)
    |--------------------------------------------------------------------------
    |
    | Catalogue faisant autorité (fourni par l'intégration KPay). Chaque
    | opérateur porte :
    |   - `code`     : code provider EXACT envoyé à KPay (identifiant unique).
    |   - `label`    : libellé affiché à l'utilisateur.
    |   - `currency` : devise LOCALE réellement débitée par cet opérateur.
    |
    | Un même pays peut proposer plusieurs devises (ex. RDC : CDF et USD), d'où
    | la devise portée PAR OPÉRATEUR et non par pays. Le montant est converti
    | depuis la base XOF vers la devise de l'opérateur choisi.
    |
    | `dial` = indicatif téléphonique (préfixé au numéro local saisi).
    */
    'countries' => [
        'BJ' => [
            'name' => 'Bénin',
            'flag' => '🇧🇯',
            'dial' => '229',
            'operators' => [
                ['code' => 'MOOV_BEN', 'label' => 'Moov Money', 'currency' => 'XOF'],
                ['code' => 'MTN_MOMO_BEN', 'label' => 'MTN MoMo', 'currency' => 'XOF'],
            ],
        ],
        'CI' => [
            'name' => "Côte d'Ivoire",
            'flag' => '🇨🇮',
            'dial' => '225',
            'operators' => [
                ['code' => 'MTN_MOMO_CIV', 'label' => 'MTN MoMo', 'currency' => 'XOF'],
                ['code' => 'ORANGE_CIV', 'label' => 'Orange Money', 'currency' => 'XOF'],
            ],
        ],
        'CM' => [
            'name' => 'Cameroun',
            'flag' => '🇨🇲',
            'dial' => '237',
            'operators' => [
                ['code' => 'MTN_MOMO_CMR', 'label' => 'MTN MoMo', 'currency' => 'XAF'],
                ['code' => 'ORANGE_CMR', 'label' => 'Orange Money', 'currency' => 'XAF'],
            ],
        ],
        'CD' => [
            'name' => 'RD Congo',
            'flag' => '🇨🇩',
            'dial' => '243',
            'operators' => [
                ['code' => 'AIRTEL_COD_CDF', 'label' => 'Airtel Money', 'currency' => 'CDF'],
                ['code' => 'ORANGE_COD_CDF', 'label' => 'Orange Money', 'currency' => 'CDF'],
                ['code' => 'VODACOM_COD_CDF', 'label' => 'Vodacom M-Pesa', 'currency' => 'CDF'],
                ['code' => 'AIRTEL_COD_USD', 'label' => 'Airtel Money (USD)', 'currency' => 'USD'],
                ['code' => 'ORANGE_COD_USD', 'label' => 'Orange Money (USD)', 'currency' => 'USD'],
                ['code' => 'VODACOM_COD_USD', 'label' => 'Vodacom M-Pesa (USD)', 'currency' => 'USD'],
            ],
        ],
        'CG' => [
            'name' => 'Congo-Brazzaville',
            'flag' => '🇨🇬',
            'dial' => '242',
            'operators' => [
                ['code' => 'AIRTEL_COG', 'label' => 'Airtel Money', 'currency' => 'XAF'],
                ['code' => 'MTN_MOMO_COG', 'label' => 'MTN MoMo', 'currency' => 'XAF'],
            ],
        ],
        'GA' => [
            'name' => 'Gabon',
            'flag' => '🇬🇦',
            'dial' => '241',
            'operators' => [
                ['code' => 'AIRTEL_GAB', 'label' => 'Airtel Money', 'currency' => 'XAF'],
            ],
        ],
        'KE' => [
            'name' => 'Kenya',
            'flag' => '🇰🇪',
            'dial' => '254',
            'operators' => [
                ['code' => 'MPESA_KEN', 'label' => 'Safaricom M-Pesa', 'currency' => 'KES'],
            ],
        ],
        'RW' => [
            'name' => 'Rwanda',
            'flag' => '🇷🇼',
            'dial' => '250',
            'operators' => [
                ['code' => 'AIRTEL_RWA', 'label' => 'Airtel Money', 'currency' => 'RWF'],
                ['code' => 'MTN_MOMO_RWA', 'label' => 'MTN MoMo', 'currency' => 'RWF'],
            ],
        ],
        'SN' => [
            'name' => 'Sénégal',
            'flag' => '🇸🇳',
            'dial' => '221',
            'operators' => [
                ['code' => 'FREE_SEN', 'label' => 'Free Money', 'currency' => 'XOF'],
                ['code' => 'ORANGE_SEN', 'label' => 'Orange Money', 'currency' => 'XOF'],
            ],
        ],
        'SL' => [
            'name' => 'Sierra Leone',
            'flag' => '🇸🇱',
            'dial' => '232',
            'operators' => [
                ['code' => 'ORANGE_SLE', 'label' => 'Orange Money', 'currency' => 'SLE'],
            ],
        ],
        'UG' => [
            'name' => 'Ouganda',
            'flag' => '🇺🇬',
            'dial' => '256',
            'operators' => [
                ['code' => 'AIRTEL_UGA', 'label' => 'Airtel Money', 'currency' => 'UGX'],
                ['code' => 'MTN_MOMO_UGA', 'label' => 'MTN MoMo', 'currency' => 'UGX'],
            ],
        ],
        'ZM' => [
            'name' => 'Zambie',
            'flag' => '🇿🇲',
            'dial' => '260',
            'operators' => [
                ['code' => 'MTN_MOMO_ZMB', 'label' => 'MTN MoMo', 'currency' => 'ZMW'],
                ['code' => 'ZAMTEL_ZMB', 'label' => 'Zamtel Kwacha', 'currency' => 'ZMW'],
            ],
        ],
    ],
];
