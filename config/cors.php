<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    |
    | Autorise l'app Flutter Web (servie sur un autre origin, ex.
    | http://localhost:xxxxx en dev) à consommer l'API. Sans ceci, le
    | navigateur bloque les requêtes cross-origin (ex. /countries revient vide
    | → « Aucun pays trouvé »). L'API mobile n'utilise pas de cookies (auth par
    | Bearer token), donc on peut ouvrir les origines sans supporter les
    | credentials.
    |
    */

    'paths' => ['api/*', 'webhooks/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
