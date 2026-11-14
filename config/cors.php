<?php

/*
|--------------------------------------------------------------------------
| CORS
|--------------------------------------------------------------------------
| Seul le frontend Tely peut appeler l'API depuis un navigateur.
| Plusieurs origines possibles via CORS_ALLOWED_ORIGINS (séparées par des virgules),
| sinon FRONTEND_URL est utilisé.
*/

$origines = array_values(array_filter(array_map(
    fn (string $origine) => rtrim(trim($origine), '/'),
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', env('FRONTEND_URL', 'http://localhost:5173')))
)));

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $origines,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 3600,

    'supports_credentials' => false,

];
