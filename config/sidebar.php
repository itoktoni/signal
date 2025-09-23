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
            'name' => 'Content',
            'icon' => 'bi-file-earmark-text',
            'menus' => [
                'profile' => [
                    'name' => 'Profile',
                    'route' => 'profile',
                    'icon' => 'bi-person',
                ],
            ],
        ],
    ],
];