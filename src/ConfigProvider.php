<?php

declare(strict_types=1);

namespace Kaiseki\WordPress\InpsydeAssets;

use Kaiseki\WordPress\InpsydeAssets\Registry\ViteManifestRegistry;
use Kaiseki\WordPress\InpsydeAssets\Registry\ViteManifestRegistryFactory;

final class ConfigProvider
{
    /**
     * @return array<mixed>
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                'factories' => [
                    ViteManifestRegistry::class => ViteManifestRegistryFactory::class,
                ],
            ],
        ];
    }
}
