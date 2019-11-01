<?php

use S3Files\Security\OAuthAuthenticator;
use S3Files\Security\Roles;
use S3Files\Security\GoogleUserProvider;

$container->loadFromExtension('security', [

    'providers' => [
        'webservice' => [
            'id' => GoogleUserProvider::class,
        ]
    ],
    'firewalls' => [
        'dev' => [
            'pattern' => '^/(_(profiler|wdt)|css|images|js|download)/',
            'security' => false,
        ],
        'secured' => [
            'pattern' => '^(/)|(/files/*)',
            'guard' => [
                'authenticators' => [
                    OAuthAuthenticator::class
                ],
            ],
            'logout' => true,
        ],
    ],

    'role_hierarchy' => Roles::getHierarchy(),

    'access_control' => [
        ['path' => '^/', 'role' => Roles::VIEW_FILES],
        ['path' => '^/files/list', 'role' => Roles::VIEW_FILES],
        ['path' => '^/files/upload', 'role' => Roles::UPLOAD_FILES],
    ],
]);
