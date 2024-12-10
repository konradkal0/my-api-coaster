<?php
declare(strict_types=1);

use App\Application\Actions\User\ListUsersAction;
use App\Application\Actions\User\ViewUserAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

/**
 * @param App $app
 */
return function (App $app) {
    // Rejestracja nowej kolejki górskiej
    $app->post('/api/coasters', function (Request $request, Response $response) {
        $data = $request->getParsedBody();
        $coasterId = uniqid('coaster_', true);
        $redis = $this->get('redis');

        // Dodanie danych kolejki do Redis
        $redis->hMSet($coasterId, [
            'operating_hours_start' => $data['operating_hours_start'] ?? '08:00',
            'operating_hours_end' => $data['operating_hours_end'] ?? '18:00',
            'wagon_count' => 0,
            'staff_count' => $data['staff_count'] ?? 10,
            'client_count' => $data['client_count'] ?? 300,
        ]);

        $response->getBody()->write(json_encode(['status' => 'success', 'coaster_id' => $coasterId]));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // Rejestracja nowego wagonu z walidacją godzin pracy i przerw
    $app->post('/api/coasters/{coasterId}/wagons', function (Request $request, Response $response, array $args) {
        $coasterId = $args['coasterId'];
        $data = $request->getParsedBody();
        $redis = $this->get('redis');

        // Sprawdzenie, czy kolejka istnieje
        if (!$redis->exists($coasterId)) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Kolejka nie istnieje']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // Pobranie godzin operacyjnych kolejki
        $operatingHoursStart = strtotime($redis->hGet($coasterId, 'operating_hours_start'));
        $operatingHoursEnd = strtotime($redis->hGet($coasterId, 'operating_hours_end'));
        $currentTime = time();

        // Sprawdzenie, czy aktualny czas mieści się w godzinach pracy
        if ($currentTime < $operatingHoursStart || $currentTime > $operatingHoursEnd) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Kolejka jest zamknięta']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Sprawdzenie ostatniego użycia wagonu
        $lastUsageTime = strtotime($redis->hGet("$coasterId:last_usage", 'last_usage_time'));
        if ($lastUsageTime && ($currentTime - $lastUsageTime) < 300) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Wagon musi odpocząć przez 5 minut']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Rejestracja nowego wagonu
        $wagonId = uniqid('wagon_', true);
        $redis->hMSet("$coasterId:$wagonId", [
            'capacity' => $data['capacity'] ?? 20,
            'speed' => $data['speed'] ?? 1.5,
            'last_usage_time' => '',
        ]);
        $redis->sAdd("$coasterId:wagons", $wagonId);

        // Zaktualizuj czas użycia wagonu
        $redis->hSet("$coasterId:last_usage", 'last_usage_time', date('Y-m-d H:i:s', $currentTime));

        // Zaktualizuj licznik wagonów
        $currentWagonCount = $redis->hGet($coasterId, 'wagon_count');
        $redis->hSet($coasterId, 'wagon_count', $currentWagonCount + 1);

        $response->getBody()->write(json_encode(['status' => 'success', 'wagon_id' => $wagonId]));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // Usunięcie wagonu
    $app->delete('/api/coasters/{coasterId}/wagons/{wagonId}', function (Request $request, Response $response, array $args) {
        $coasterId = $args['coasterId'];
        $wagonId = $args['wagonId'];
        $redis = $this->get('redis');

        // Sprawdzenie, czy wagon istnieje
        if (!$redis->exists("$coasterId:$wagonId")) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Wagon nie istnieje']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // Usunięcie wagonu
        $redis->del("$coasterId:$wagonId");
        $redis->sRem("$coasterId:wagons", $wagonId);

        // Zaktualizuj licznik wagonów
        $currentWagonCount = $redis->hGet($coasterId, 'wagon_count');
        $redis->hSet($coasterId, 'wagon_count', $currentWagonCount - 1);

        $response->getBody()->write(json_encode(['status' => 'success', 'message' => 'Wagon usunięty']));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // Zmiana kolejki górskiej
    $app->put('/api/coasters/{coasterId}', function (Request $request, Response $response, array $args) {
        $coasterId = $args['coasterId'];
        $data = $request->getParsedBody();
        $redis = $this->get('redis');

        // Pobranie obecnych danych kolejki
        $currentData = $redis->hGetAll($coasterId);
        if (!$currentData) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Kolejka nie istnieje']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // Zachowanie długości trasy
        $data['dl_trasy'] = $currentData['dl_trasy'];

        $redis->hMSet($coasterId, $data);

        $response->getBody()->write(json_encode(['status' => 'success', 'message' => 'Dane kolejki zaktualizowane']));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // Ustawianie godzin operacyjnych
    $app->put('/api/coasters/{coasterId}/operating-hours', function (Request $request, Response $response, array $args) {
        $coasterId = $args['coasterId'];
        $data = $request->getParsedBody();
        $redis = $this->get('redis');

        // Sprawdzenie, czy kolejka istnieje
        if (!$redis->exists($coasterId)) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Kolejka nie istnieje']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // Ustawienie godzin operacyjnych
        $redis->hSet($coasterId, 'operating_hours_start', $data['start']);
        $redis->hSet($coasterId, 'operating_hours_end', $data['end']);

        $response->getBody()->write(json_encode(['status' => 'success', 'message' => 'Godziny operacyjne zaktualizowane']));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // Inne trasy
    $app->options('/{routes:.*}', function (Request $request, Response $response) {
        // Obsługa CORS dla zapytań OPTIONS
        return $response;
    });

    $app->get('/', function (Request $request, Response $response) {
        $response->getBody()->write('Poprawne działanie!');
        return $response;
    });

    $app->group('/users', function (Group $group) {
        $group->get('', ListUsersAction::class);
        $group->get('/{id}', ViewUserAction::class);
    });
};
