<?php

declare(strict_types=1);

use App\Application\Settings\SettingsInterface;
use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        'redis' => function (ContainerInterface $c) {
            try {
                $settings = $c->get(SettingsInterface::class)->get('redis');

                if (empty($settings['host']) || empty($settings['port']) || !isset($settings['database'])) {
                    throw new \RuntimeException('Ustawienia Redisa są niekompletne lub nie zostały znalezione.');
                }

                $redis = new Redis();
                $redis->connect($settings['host'], (int)$settings['port']);
                $redis->select((int)$settings['database']);

                return $redis;
            } catch (\RedisException $e) {
                throw new \RuntimeException('Nie udało się połączyć z Redisem: ' . $e->getMessage(), 0, $e);
            } catch (\Exception $e) {
                throw new \RuntimeException('Błąd inicjalizacji Redisa: ' . $e->getMessage(), 0, $e);
            }
        },

        LoggerInterface::class => function (ContainerInterface $c) {
            try {
                $settings = $c->get(SettingsInterface::class)->get('logger');

                if (empty($settings['name']) || empty($settings['path']) || !isset($settings['level'])) {
                    throw new \RuntimeException('Ustawienia loggera są niekompletne lub nie zostały znalezione.');
                }

                $logger = new Logger($settings['name']);
                $processor = new UidProcessor();
                $logger->pushProcessor($processor);

                $handler = new StreamHandler($settings['path'], (int)$settings['level']);
                $logger->pushHandler($handler);

                return $logger;
            } catch (\Exception $e) {
                throw new \RuntimeException('Błąd inicjalizacji loggera: ' . $e->getMessage(), 0, $e);
            }
        },
    ]);
};