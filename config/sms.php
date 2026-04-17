<?php

return [
    'enabled' => env('SMS_ENABLED', true),
    'api_key' => env('SMS_API_KEY'),
    'sender_id' => env('SMS_SENDER_ID'),
    'type' => env('SMS_TYPE', 'text'),
    'label' => env('SMS_LABEL', 'transactional'),
    'base_url' => 'https://msg.mram.com.bd',
];