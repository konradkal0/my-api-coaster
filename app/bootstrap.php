<?php

declare(strict_types=1);

use DI\ContainerBuilder;

require __DIR__ . '/../vendor/autoload.php';

$containerBuilder = new ContainerBuilder();

// Określenie środowiska
$environment = getenv('APP_ENV') ?: 'production';
$isProduction = $environment === 'production';

// Wczytanie ustawień
$settings = require __DIR__ . '/settings.php';
if (is_callable($settings)) {
    $settings($containerBuilder);
} else {
    throw new \RuntimeException('Settings file did not return a callable function.');
}

// Wczytanie zależności
$dependencies = require __DIR__ . '/dependencies.php';
if (is_callable($dependencies)) {
    $dependencies($containerBuilder);
} else {
    throw new \RuntimeException('Dependencies file did not return a callable function.');
}

// Wczytanie repozytoriów (jeśli istnieją)
$repositoriesFile = __DIR__ . '/repositories.php';
if (file_exists($repositoriesFile)) {
    $repositories = require $repositoriesFile;
    if (is_callable($repositories)) {
        $repositories($containerBuilder);
    } else {
        throw new \RuntimeException('Repositories file did not return a callable function.');
    }
}

// Zbudowanie kontenera
$container = $containerBuilder->build();

return $container;
