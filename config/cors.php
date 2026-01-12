<?php

return [
    'paths' => ['api/*', 'storage/*'],  // ← Add 'storage/*' here

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000',
        'https://basma-front-end-next-js-xotm.vercel.app',
        'https://basma-front-end-next-js.vercel.app',
        'https://ibtikarbd.com',
        'https://eyarafashion.xyz',  // ← Add your production domain
        'https://www.eyarafashion.xyz',  // ← Add www version too
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Content-Length', 'Content-Type'],  // ← Add these

    'max_age' => 0,

    'supports_credentials' => false,  // ← Change to false when using wildcard origins
];