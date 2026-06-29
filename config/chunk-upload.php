<?php
/**
 * Config du package pion/laravel-chunk-upload (réception chunkée des vidéos
 * uploadées vers la Bunny Library). Publiée et adaptée pour ABBEV.
 *
 * @see https://github.com/pionl/laravel-chunk-upload
 */

return [
    /*
     * Stockage des chunks partiels : storage/app/chunks, disque local.
     */
    'storage' => [
        'chunks' => 'chunks',
        'disk'   => 'local',
    ],

    'clear' => [
        /*
         * Purge automatique des chunks orphelins de plus de 6h (un upload de
         * plusieurs Go peut légitimement durer longtemps).
         */
        'timestamp' => '-6 HOURS',
        'schedule'  => [
            'enabled' => true,
            'cron'    => '25 * * * *', // toutes les heures à la 25e minute
        ],
    ],

    'chunk' => [
        /*
         * Nommage déterministe des chunks (sans session ni IP/navigateur) :
         * le nom original + l'identifiant resumable suffisent à l'unicité par
         * upload. Évite que les chunks deviennent introuvables si la session
         * est régénérée pendant un long upload.
         */
        'name' => [
            'use' => [
                'session' => false,
                'browser' => false,
            ],
        ],
    ],

    'handlers' => [
        'custom'   => [],
        'override' => [],
    ],
];
