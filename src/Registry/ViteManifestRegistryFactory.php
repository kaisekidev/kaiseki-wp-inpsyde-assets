<?php

declare(strict_types=1);

namespace Kaiseki\WordPress\InpsydeAssets\Registry;

use Inpsyde\Assets\Asset;
use Kaiseki\Config\Config;
use Kaiseki\WordPress\InpsydeAssets\Loader\ViteManifestLoader;
use Kaiseki\WordPress\InpsydeAssets\OutputFilter\ModuleTypeScriptOutputFilter;
use Kaiseki\WordPress\InpsydeAssets\ViteClient\ViteClient;
use Psr\Container\ContainerInterface;

use function is_callable;

final class ViteManifestRegistryFactory
{
    public function __invoke(ContainerInterface $container): ViteManifestRegistry
    {
        $config = Config::fromContainer($container);
        $baseFilter = fn(Asset $asset, ViteClient $viteClient, string $handle): Asset => $asset;
        $scriptFilter = $config->get('vite_manifest.script_filter', $baseFilter);
        $styleFilter = $config->get('vite_manifest.style_filter', $baseFilter);

        return new ViteManifestRegistry(
            $container->get(ViteManifestLoader::class),
            $container->get(ModuleTypeScriptOutputFilter::class),
            $container->get(ViteClient::class),
            $config->array('vite_manifest.files', []),
            is_callable($scriptFilter) ? $scriptFilter : $baseFilter,
            $config->array('vite_manifest.scripts', []),
            is_callable($styleFilter) ? $styleFilter : $baseFilter,
            $config->array('vite_manifest.styles', []),
            $config->bool('vite_manifest.autoload', false),
            $config->get('vite_manifest.directory_url', ''),
            $config->string('vite_manifest.handle_prefix', ''),
            $config->bool('vite_manifest.es_modules', true),
        );
    }
}
