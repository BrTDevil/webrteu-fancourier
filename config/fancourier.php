<?php

declare(strict_types=1);

return [

    'base_url' => env('FANCOURIER_BASE_URL', 'https://api.fancourier.ro'),

    /*
    | Utilizatorul și parola selfAWB, plus id-ul de client din selfAWB.
    */
    'username' => env('FANCOURIER_USERNAME'),
    'password' => env('FANCOURIER_PASSWORD'),
    'client_id' => env('FANCOURIER_CLIENT_ID'),

    'default_service' => env('FANCOURIER_DEFAULT_SERVICE', 'Standard'),
    'fanbox_service' => env('FANCOURIER_FANBOX_SERVICE', 'FANbox'),

    /*
    | Cine plătește transportul: sender | recipient.
    */
    'payment' => env('FANCOURIER_PAYMENT', 'sender'),

    /*
    | Rambursul: cash | cont (cont colector).
    */
    'cod_payment' => env('FANCOURIER_COD_PAYMENT', 'cash'),

    'token_cache_ttl' => 60 * 60 * 20,
    'timeout' => (int) env('FANCOURIER_TIMEOUT', 15),

    'tables' => [
        'localities' => 'fancourier_localities',
        'pickup_points' => 'fancourier_pickup_points',
    ],

];
