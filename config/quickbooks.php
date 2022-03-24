<?php

return [
    'invoice' => [
        'austax' => '',
        'overseastax' => '',
        'paymentaccount' => '',
        'shippingtax' => '',
        'shipitem' => ''
    ],

    'customer' => [
        'model' => 'App\Models\User',
        'conditions' => [
            'role' => [
                'Approved'
            ],
            'with' => [
                'client'
            ],
            'has' => [
                'orders'
            ],
            'where' => [
                'sync_failed',
                '<',
                3
            ]
        ],

        'qb_customer_id' => 'qb_customer_id',
        'fully_qualified_name' => 'name',
        'email_address' => 'email',
        'phone' => 'phone',
        'display_name' => 'name',
        'given_name' => 'client.firstName',
        'family_name' => 'client.lastName',
        'company_name' => 'businessName',
        'address_line_1' => 'address',
        'city' => 'city',
        'suburb' => 'suburb',
        'postcode' => 'postcode',
        'country' => 'country',

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