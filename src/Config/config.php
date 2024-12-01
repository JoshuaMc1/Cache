<?php

return [
    'driver' => 'file',
    'prefix' => 'c_',
    'ttl' => 3600,
    'drivers' => [
        'file' => [
            'path' => sprintf('%s/../storage/.cache',  __DIR__),
        ],
        'sqlite' => [
            'database' => sprintf('%s/../storage/.cache/database.sqlite',  __DIR__),
        ],
        'mysql' => [
            'host' => '127.0.0.1',
            'port' => 3306,
            'username' => 'root',
            'password' => '',
            'database' => '',
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci'
        ]
    ]
];
