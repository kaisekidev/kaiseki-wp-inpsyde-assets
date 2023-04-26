<?php

declare(strict_types=1);

namespace Kaiseki\WordPress\InpsydeAssets\Registry;

use Inpsyde\Assets\Asset;
use Kaiseki\Config\Config;
use Kaiseki\WordPress\InpsydeAssets\Loader\ViteManifestLoader;
use Kaiseki\WordPress\InpsydeAssets\OutputFilter\ModuleTypeScriptOutputFilter;
use Psr\Container\ContainerInterface;

final class ViteManifestRegistryFactory
{
    public function __invoke(ContainerInterface $container): ViteManifestRegistry
    {
        $config = Config::get($container);
        $baseFilter = fn(Asset $asset, string $handle): Asset => $asset;
        return new ViteManifestRegistry(
            $container->get(ViteManifestLoader::class),
            $container->get(ModuleTypeScriptOutputFilter::class),
            /** @phpstan-ignore-next-line */
            $config->array('vite_manifest/files', []),
            $config->callable('vite_manifest/script_filter', $baseFilter),
            /** @phpstan-ignore-next-line */
            $config->array('vite_manifest/scripts', []),
            $config->callable('vite_manifest/style_filter', $baseFilter),
            /** @phpstan-ignore-next-line */
            $config->array('vite_manifest/styles', []),
            $config->bool('vite_manifest/autoload', false),
            $config->string('vite_manifest/directory_url', ''),
            $config->string('vite_manifest/handle_prefix', ''),
            $config->bool('vite_manifest/es_modules', true),
        );
    }
}
