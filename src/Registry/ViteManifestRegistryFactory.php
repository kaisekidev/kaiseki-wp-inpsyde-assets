<?php

declare(strict_types=1);

namespace Kaiseki\WordPress\InpsydeAssets\Registry;

use Inpsyde\Assets\Asset;
use Kaiseki\Config\Config;
use Kaiseki\WordPress\InpsydeAssets\Loader\ViteManifestLoader;
use Kaiseki\WordPress\InpsydeAssets\OutputFilter\ModuleTypeScriptOutputFilter;
use Kaiseki\WordPress\InpsydeAssets\ViteClient\ViteClient;
use Psr\Container\ContainerInterface;

/**
 * @phpstan-import-type ScriptFilterCallable from ViteManifestRegistry
 * @phpstan-import-type StyleFilterCallable from ViteManifestRegistry
 */
final class ViteManifestRegistryFactory
{
    public function __invoke(ContainerInterface $container): ViteManifestRegistry
    {
        $config = Config::get($container);
        $baseFilter = fn(Asset $asset, string $handle): Asset => $asset;
        /** @var list<string> $files */
        $files = $config->array('vite_manifest/files', []);
        /** @var array<string, ScriptFilterCallable|bool> $scripts */
        $scripts = $config->array('vite_manifest/scripts', []);
        /** @var array<string, StyleFilterCallable|bool> $styles */
        $styles = $config->array('vite_manifest/styles', []);
        return new ViteManifestRegistry(
            $container->get(ViteManifestLoader::class),
            $container->get(ModuleTypeScriptOutputFilter::class),
            $container->get(ViteClient::class),
            $files,
            $config->callable('vite_manifest/script_filter', $baseFilter),
            $scripts,
            $config->callable('vite_manifest/style_filter', $baseFilter),
            $styles,
            $config->bool('vite_manifest/autoload', false),
            $config->get('vite_manifest/directory_url', ''),
            $config->string('vite_manifest/handle_prefix', ''),
            $config->bool('vite_manifest/es_modules', true),
        );
    }
}
