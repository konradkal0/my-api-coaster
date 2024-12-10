<?php

declare(strict_types=1);

use App\Application\Settings\Settings;
use App\Application\Settings\SettingsInterface;
use DI\ContainerBuilder;
use Monolog\Logger;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        SettingsInterface::class => function () {
            // Konfiguracja ścieżki logów
            $logPath = isset($_ENV['docker']) && $_ENV['docker'] === 'true'
                ? 'php://stdout'
                : __DIR__ . '/../logs/app.log';

            // Sprawdzenie i tworzenie katalogu logów
            $logDir = dirname($logPath);
            if (!is_dir($logDir)) {
                if (!mkdir($logDir, 0777, true) && !is_dir($logDir)) {
                    throw new RuntimeException(sprintf('Nie można utworzyć katalogu logów: %s', $logDir));
                }
            }

            // Zwróć obiekt ustawień
            return new Settings([
                'displayErrorDetails' => true, // Pokazywanie szczegółów błędów (ustaw na false w produkcji)
                'logError'            => true, // Logowanie błędów
                'logErrorDetails'     => true, // Logowanie szczegółów błędów
                'redis' => [
                    'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1', // Możliwość nadpisania przez zmienne środowiskowe
                    'port' => (int) ($_ENV['REDIS_PORT'] ?? 6379), // Rzutowanie na int dla bezpieczeństwa
                    'database' => (int) ($_ENV['REDIS_DATABASE'] ?? 1), // Rzutowanie na int
                ],
                'logger' => [
                    'name' => $_ENV['LOGGER_NAME'] ?? 'slim-app', // Możliwość konfiguracji nazwy loggera
                    'path' => $logPath,
                    'level' => Logger::DEBUG, // Domyślnie poziom DEBUG
                ],
            ]);
        },
    ]);
};
