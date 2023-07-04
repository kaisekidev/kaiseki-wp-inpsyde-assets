<?php

declare(strict_types=1);

namespace Kaiseki\WordPress\InpsydeAssets\ViteClient;

use Kaiseki\Config\Config;
use Kaiseki\WordPress\Environment\EnvironmentInterface;
use Psr\Container\ContainerInterface;

final class ViteClientFactory
{
    public function __invoke(ContainerInterface $container): ViteClient
    {
        $config = Config::get($container);
        return new ViteClient(
            $container->get(EnvironmentInterface::class),
            $config->string('vite_client/host', 'localhost'),
            $config->int('vite_client/port', 5173),
        );
    }
}
