<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use React\EventLoop\Factory;
use Predis\Client as RedisClient;

// Połączenie z Redis
$container = require __DIR__ . '/app/bootstrap.php';
$redis = $container->get('redis');

// Dodawanie danych testowych do Redis
try {
    $redis->hMSet('coaster_test_1', [
        'godziny_od' => '08:00',
        'godziny_do' => '18:00',
        'liczba_personelu' => 15,
        'liczba_klientow' => 200,
    ]);
    $redis->sAdd('coaster_test_1:wagons', 'wagon_1', 'wagon_2', 'wagon_3', 'wagon_4', 'wagon_5');

    $redis->hMSet('coaster_test_2', [
        'godziny_od' => '09:00',
        'godziny_do' => '17:00',
        'liczba_personelu' => 8,
        'liczba_klientow' => 120,
    ]);
    $redis->sAdd('coaster_test_2:wagons', 'wagon_1', 'wagon_2', 'wagon_3', 'wagon_4');

    echo "Dane testowe zostały dodane do Redis.\n";
} catch (\Exception $e) {
    die("Błąd podczas dodawania danych testowych: " . $e->getMessage() . "\n");
}

// Katalog logów
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    if (!mkdir($logDir, 0777, true) && !is_dir($logDir)) {
        die("Nie udało się utworzyć katalogu logów: $logDir\n");
    }
}

// Debugowanie katalogu logów
if (!is_writable($logDir)) {
    die("Katalog logów nie jest zapisywalny: $logDir\n");
} else {
    echo "Katalog logów jest zapisywalny: $logDir\n";
}

// Testowy zapis logów
$testLogFile = "$logDir/test.log";
$testLogData = "Testowy zapis: " . date('Y-m-d H:i:s') . "\n";

$result = file_put_contents($testLogFile, $testLogData, FILE_APPEND);
if ($result === false) {
    die("Nie udało się zapisać testowego logu do pliku: $testLogFile\n");
} else {
    echo "Testowy log zapisany do pliku: $testLogFile\n";
}

// Utworzenie pętli ReactPHP
$loop = Factory::create();

// Funkcja monitorująca kolejki
function monitorCoasters($redis, $logDir)
{
    $coasterKeys = $redis->keys('coaster_*');
    $logEntries = [];

    // Debugowanie danych w Redis
    if (empty($coasterKeys)) {
        echo "[" . date('H:i') . "] Brak zarejestrowanych kolejek w Redis.\n";
        echo "Dodaj dane testowe do Redis, aby kontynuować monitorowanie.\n";
        return [];
    } else {
        echo "\n[Godzina " . date('H:i') . "]\n";
    }

    foreach ($coasterKeys as $coasterKey) {
        // Pomijanie kluczy dodatkowych, np. :wagons
        if (str_contains($coasterKey, ':')) {
            continue; 
        }

        // Pobieranie danych kolejki
        $coasterData = $redis->hGetAll($coasterKey);
        if (empty($coasterData)) {
            echo "Błąd: Nie znaleziono danych dla kolejki: $coasterKey\n";
            continue; // Przejdź do kolejnej kolejki
        }

        $wagonIds = $redis->sMembers("$coasterKey:wagons");
        if (!is_array($wagonIds)) {
            echo "Błąd: Nie można odczytać wagonów dla kolejki: $coasterKey\n";
            continue; // Przejdź do kolejnej kolejki
        }

        $wagonCount = count($wagonIds);
        $liczbaPersonelu = (int)($coasterData['liczba_personelu'] ?? 0);
        $liczbaKlientow = (int)($coasterData['liczba_klientow'] ?? 0);

        // Wyliczanie wymaganej liczby personelu i wagonów
        $requiredStaff = max(10, ceil($liczbaKlientow / 20));
        $requiredWagons = max(5, ceil($liczbaKlientow / 50));

        // Analiza problemów
        $problems = [];
        if ($wagonCount < $requiredWagons) {
            $problems[] = "Brakuje " . ($requiredWagons - $wagonCount) . " wagonów";
        }
        if ($liczbaPersonelu < $requiredStaff) {
            $problems[] = "Brakuje " . ($requiredStaff - $liczbaPersonelu) . " pracowników";
        }

        // Status kolejki
        $status = empty($problems) ? "OK" : implode(", ", $problems);

        // Wyświetlanie danych w konsoli w wymaganym formacie
        echo "\n[Kolejka " . ucfirst($coasterKey) . "]\n";
        echo "1. Godziny działania: {$coasterData['godziny_od']} - {$coasterData['godziny_do']}\n";
        echo "2. Liczba wagonów: $wagonCount/$requiredWagons\n";
        echo "3. Dostępny personel: $liczbaPersonelu/$requiredStaff\n";
        echo "4. Klienci dziennie: $liczbaKlientow\n";
        echo "5. " . (empty($problems) ? "Status: $status" : "Problem: $status") . "\n";

        // Zbieranie logów
        $logEntries[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'coaster' => $coasterKey,
            'status' => $status,
        ];

        // Zapis problemów do logów z debugowaniem
        if (!empty($problems)) {
            $logEntry = json_encode([
                'timestamp' => date('Y-m-d H:i:s'),
                'coaster' => $coasterKey,
                'problems' => $problems,
            ]) . "\n";

            $result = file_put_contents("$logDir/monitor.log", $logEntry, FILE_APPEND);

            if ($result === false) {
                echo "Nie udało się zapisać logów do pliku: $logDir/monitor.log\n";
                echo "Debug: Próbowano zapisać dane: $logEntry\n";
            } else {
                echo "Log zapisany do pliku: $logDir/monitor.log\n";
            }
        }
    }

    return $logEntries;
}

// Cykl monitorowania co 60 sekund z debugowaniem
$loop->addPeriodicTimer(60, function () use ($redis, $logDir) {
    echo "[" . date('H:i') . "] Rozpoczynam monitorowanie...\n";
    monitorCoasters($redis, $logDir);
    echo "[" . date('H:i') . "] Monitorowanie zakończone.\n";
});

// Uruchomienie pętli
$loop->run();