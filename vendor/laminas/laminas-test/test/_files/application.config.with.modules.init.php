<?php

declare(strict_types=1);

return [
    'modules'                 => [
        'Laminas\Router',
        'Qux', // Qux will load Foo during module init event
    ],
    'module_listener_options' => [
        'config_static_paths' => [],
        'module_paths'        => [
            'Qux' => __DIR__ . '/modules-path/with-subdir/Qux',
            'Foo' => __DIR__ . '/modules-path/with-subdir/Foo',
        ],
    ],
];
