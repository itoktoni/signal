<?php

return [
    'groups' => [
        'dashboard' => [
            'name' => 'Home',
            'icon' => 'bi-house',
            'menus' => [
                'dashboard' => [
                    'name' => 'Dashboard',
                    'route' => 'dashboard',
                    'icon' => 'bi-house',
                ],
            ],
        ],
        'system' => [
            'name' => 'System',
            'icon' => 'bi-gear',
            'menus' => [
                'users' => [
                    'name' => 'Users',
                    'route' => 'user.getData',
                    'icon' => 'bi-people',
                ],
            ],
        ],
        'content' => [
            'name' => 'Crypto',
            'icon' => 'bi-currency-exchange',
            'menus' => [
                'coin' => [
                    'name' => 'Coin',
                    'route' => 'coin.getData',
                    'icon' => 'bi-currency-bitcoin',
                ],
            ],
        ],
    ],
];
