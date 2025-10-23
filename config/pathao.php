<?php

return [
    'base_url' => env('PATHAO_BASE_URL', 'https://courier-api-sandbox.pathao.com'),
    'client_id' => env('PATHAO_CLIENT_ID'),
    'client_secret' => env('PATHAO_CLIENT_SECRET'),
    'username' => env('PATHAO_USERNAME'),
    'password' => env('PATHAO_PASSWORD'),
    'grant_type' => env('PATHAO_GRANT_TYPE', 'password'),
    'store_id' => env('PATHAO_STORE_ID'),
];
