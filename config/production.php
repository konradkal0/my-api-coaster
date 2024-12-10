<?php
return [
    'settings' => [
        'displayErrorDetails' => false,
        'logger' => [
            'name' => 'app',
            'path' => __DIR__ . '/../logs/app.log',
            'level' => \Monolog\Logger::WARNING, // Rejestrowanie tylko warning i error
        ],
        'redis' => [
            'host' => '127.0.0.1',
            'port' => 6379,
            'database' => 1, // Oddzielna baza dla produkcji
        ],
    ],
];
