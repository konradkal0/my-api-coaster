<?php
declare(strict_types=1);

use App\Application\Handlers\HttpErrorHandler;
use App\Application\Handlers\ShutdownHandler;
use App\Application\ResponseEmitter\ResponseEmitter;
use App\Application\Settings\SettingsInterface;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;

require __DIR__ . '/../vendor/autoload.php';

try {
    // Załaduj kontener z pliku bootstrap.php
    $container = require __DIR__ . '/../app/bootstrap.php';

    // Utwórz aplikację
    AppFactory::setContainer($container);
    $app = AppFactory::create();
    $callableResolver = $app->getCallableResolver();

    // Wczytaj middleware
    $middleware = require __DIR__ . '/../app/middleware.php';
    if (is_callable($middleware)) {
        $middleware($app);
    } else {
        throw new \RuntimeException('Middleware file did not return a callable.');
    }

    // Wczytaj trasy
    $routes = require __DIR__ . '/../app/routes.php';
    if (is_callable($routes)) {
        $routes($app);
    } else {
        throw new \RuntimeException('Routes file did not return a callable.');
    }

    /** @var SettingsInterface $settings */
    $settings = $container->get(SettingsInterface::class);

    // Pobierz ustawienia aplikacji
    $displayErrorDetails = $settings->get('displayErrorDetails');
    $logError = $settings->get('logError');
    $logErrorDetails = $settings->get('logErrorDetails');

    // Utwórz obiekt żądania z globalnych zmiennych
    $serverRequestCreator = ServerRequestCreatorFactory::create();
    $request = $serverRequestCreator->createServerRequestFromGlobals();

    // Utwórz handler błędów
    $responseFactory = $app->getResponseFactory();
    $errorHandler = new HttpErrorHandler($callableResolver, $responseFactory);

    // Utwórz handler zamknięcia
    $shutdownHandler = new ShutdownHandler($request, $errorHandler, $displayErrorDetails);
    register_shutdown_function([$shutdownHandler, '__invoke']);

    // Dodaj middleware routingu
    $app->addRoutingMiddleware();

    // Dodaj middleware parsowania ciała żądania
    $app->addBodyParsingMiddleware();

    // Dodaj middleware błędów
    $errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, $logError, $logErrorDetails);
    $errorMiddleware->setDefaultErrorHandler($errorHandler);

    // Uruchom aplikację i wyślij odpowiedź
    $response = $app->handle($request);
    $responseEmitter = new ResponseEmitter();
    $responseEmitter->emit($response);

} catch (\Throwable $e) {
    // Obsługa krytycznych błędów
    $errorMessage = "[" . date('Y-m-d H:i:s') . "] Krytyczny błąd: " . $e->getMessage() . "\n";
    error_log($errorMessage);

    if (php_sapi_name() === 'cli') {
        echo $errorMessage;
    } else {
        header('Content-Type: text/plain; charset=utf-8', true, 500);
        echo "Wystąpił krytyczny błąd aplikacji. Skontaktuj się z administratorem.\n";
    }
    exit(1);
}
