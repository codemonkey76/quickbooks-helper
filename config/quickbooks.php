<?php

return [
    'invoice' => [
        'austax' => '',
        'overseastax' => '',
        'paymentaccount' => '',
        'shippingtax' => '',
        'shipitem' => ''
    ],

    'data_service' => [
        'auth_mode' => 'oauth2',
        'base_url' => env('QUICKBOOKS_API_URL', config('app.env') === 'production' ? 'Production' : 'Development'),
        'client_id' => env('QUICKBOOKS_CLIENT_ID'),
        'client_secret' => env('QUICKBOOKS_CLIENT_SECRET'),
        'scope' => 'com.intuit.quickbooks.accounting'
    ],

    'logging' => [
        'enabled' => env('QUICKBOOKS_DEBUG', config('app.debug')),

        'location' => storage_path('logs')
    ],

    'route' => [
        'middleware' => [
            'authenticated' => 'auth',
            'default' => 'web'
        ],

        'paths' => [
            'connect' => 'connect',
            'disconnect' => 'disconnect',
            'token' => 'token'
        ],

        'prefix' => 'quickbooks'
    ],

    'user' => [
        'keys' => [
            'foreign' => 'user_id',
            'owner' => 'id'
        ],
        'model' => 'App\Models\User'
    ]
];