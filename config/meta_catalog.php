<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Meta Catalog Feed
    |--------------------------------------------------------------------------
    |
    | Meta fetches the generated CSV feed on the schedule configured in
    | Commerce Manager. Product IDs intentionally stay equal to products.id so
    | they match the content_ids currently sent by Pixel and Conversions API.
    |
    */

    'brand' => env('META_CATALOG_BRAND', 'Eyara Fashion'),

    'currency' => env('META_CATALOG_CURRENCY', 'BDT'),

    'storefront_url' => env('META_CATALOG_STOREFRONT_URL', 'https://eyarafashion.xyz'),

    'product_url_pattern' => env(
        'META_CATALOG_PRODUCT_URL_PATTERN',
        '/frontEnd/product-page/{id}'
    ),

    'image_base_url' => env('META_CATALOG_IMAGE_BASE_URL', env('APP_URL', 'http://localhost')),
];
