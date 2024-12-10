<?php
return [
    'settings' => [
        'displayErrorDetails' => true, // Wyświetlanie błędów
        'logger' => [
            'name' => 'app',
            'path' => __DIR__ . '/../logs/app.log',
            'level' => \Monolog\Logger::DEBUG, // Rejestrowanie wszystkich typów logów
        ],
        'redis' => [
            'host' => '127.0.0.1',
            'port' => 6379,
            'database' => 0, // Oddzielna baza dla deweloperki
        ],
    ],
];
