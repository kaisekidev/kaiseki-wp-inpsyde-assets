<?php

declare(strict_types=1);

namespace Kaiseki\WordPress\InpsydeAssets\Registry;

use Closure;
use Inpsyde\Assets\Asset;
use Inpsyde\Assets\AssetManager;
use Inpsyde\Assets\Script;
use Inpsyde\Assets\Style;
use Kaiseki\WordPress\Hook\HookCallbackProviderInterface;
use Kaiseki\WordPress\InpsydeAssets\Loader\ViteManifestLoader;
use Kaiseki\WordPress\InpsydeAssets\OutputFilter\ModuleTypeScriptOutputFilter;
use Kaiseki\WordPress\InpsydeAssets\ViteClient\ViteClient;

use function add_action;
use function array_merge;
use function array_reduce;
use function array_unshift;
use function array_values;
use function count;
use function is_callable;
use function is_string;
use function ltrim;

/**
 * @phpstan-type AssetFilterCallable callable(Asset $asset, ViteClient $viteClient, string $handle): Asset
 * @phpstan-type ScriptFilterCallable callable(Script $script, ViteClient $viteClient, string $handle): Script
 * @phpstan-type StyleFilterCallable callable(Style $style, ViteClient $viteClient, string $handle): Style
 * @phpstan-type ViteManifestCallback callable(ViteClient $viteClient): string|null
 * @phpstan-type DirectoryUrlCallback callable(ViteClient $viteClient): string
 */
class ViteManifestRegistry implements HookCallbackProviderInterface
{
    private ?Closure $scriptFilter;
    private ?Closure $styleFilter;

    /**
     * @param ViteManifestLoader                       $loader
     * @param ModuleTypeScriptOutputFilter             $esModuleFilter
     * @param ViteClient                               $viteClient
     * @param list<string|ViteManifestCallback|null>   $viteManifests
     * @param ScriptFilterCallable|null                $scriptFilter
     * @param array<string, ScriptFilterCallable|bool> $scriptFilters
     * @param StyleFilterCallable|null                 $styleFilter
     * @param array<string, StyleFilterCallable|bool>  $styleFilters
     * @param bool                                     $autoload
     * @param string                                   $handlePrefix
     * @param bool                                     $esModules
     */
    public function __construct(
        private readonly ViteManifestLoader $loader,
        private readonly ModuleTypeScriptOutputFilter $esModuleFilter,
        private readonly ViteClient $viteClient,
        private readonly array $viteManifests = [],
        ?callable $scriptFilter = null,
        private readonly array $scriptFilters = [],
        ?callable $styleFilter = null,
        private readonly array $styleFilters = [],
        private readonly bool $autoload = true,
        mixed $directoryUrl = '',
        private readonly string $handlePrefix = '',
        private readonly bool $esModules = true,
    ) {
        $this->scriptFilter = is_callable($scriptFilter) ? $scriptFilter(...) : null;
        $this->styleFilter = is_callable($styleFilter) ? $styleFilter(...) : null;

        if (is_string($directoryUrl) && $directoryUrl !== '') {
            $directoryUrl = is_callable($directoryUrl) ? $directoryUrl($this->viteClient) : $directoryUrl;
            $this->loader->withDirectoryUrl($directoryUrl);
        } elseif (is_callable($directoryUrl)) {
            $this->loader->withDirectoryUrl($directoryUrl($this->viteClient));
        }

        if ($handlePrefix === '') {
            return;
        }

        $this->loader->withHandlePrefix($handlePrefix);
    }

    public function registerHookCallbacks(): void
    {
        add_action(AssetManager::ACTION_SETUP, [$this, 'registerAssets']);
    }

    /**
     * Hook callback to register assets.
     *
     * @param AssetManager $assetManager
     *
     * @return void
     */
    public function registerAssets(AssetManager $assetManager): void
    {
        $assets = $this->loadAssets();

        $filteredAsset = $this->filterAssets($assets);

        if (count($filteredAsset) === 0) {
            return;
        }

        $assetManager->register(...$filteredAsset);
    }

    /**
     * @return list<Asset>
     */
    protected function loadAssets(): array
    {
        $assets = [];

        foreach ($this->viteManifests as $viteManifest) {
            if (is_callable($viteManifest)) {
                $viteManifest = $viteManifest($this->viteClient);
            }

            if ($viteManifest === null) {
                continue;
            }

            $assets = array_merge($assets, $this->loader->load($viteManifest));
        }

        return $assets;
    }

    /**
     *
     * @param list<Asset> $assets
     *
     * @return list<Asset>
     */
    protected function filterAssets(array $assets): array
    {
        return array_reduce(
            $assets,
            function (array $carry, Asset $asset): array {
                $filteredAsset = $this->filterAsset($asset);

                if ($filteredAsset !== null) {
                    $carry[] = $filteredAsset;
                }

                return $carry;
            },
            []
        );
    }

    /**
     * Filter asset.
     *
     * @param Asset $asset
     *
     * @return Asset|null
     */
    private function filterAsset(Asset $asset): ?Asset
    {
        $handle = $asset->handle();

        $isScript = $asset instanceof Script;

        $typeFilter = $isScript ? $this->scriptFilter : $this->styleFilter;

        if (is_callable($typeFilter)) {
            $asset = $typeFilter($asset, $this->viteClient, $handle);
        }

        if ($isScript && $this->esModules) {
            $asset->withFilters($this->esModuleFilter);
        }

        $assetFilter = $this->getFilter(
            $handle,
            $isScript ? $this->scriptFilters : $this->styleFilters
        );

        if (!is_callable($assetFilter)) {
            if ($this->autoload === true && $assetFilter !== false) {
                return $asset;
            }
            if ($this->autoload === false && $assetFilter === true) {
                return $asset;
            }
            return  null;
        }

        return $assetFilter($asset, $this->viteClient, $handle);
    }

    /**
     * Get filter by handle.
     *
     * @param string $handle
     * @param array<string, ScriptFilterCallable|StyleFilterCallable|bool> $filters
     *
     * @return ScriptFilterCallable|StyleFilterCallable|null
     */
    private function getFilter(string $handle, array $filters): callable|bool|null
    {
        $handleWithoutPrefix = \Safe\preg_replace(
            '/^' . preg_quote($this->handlePrefix, '/') . '/',
            '',
            $handle
        );

        foreach ($filters as $filterHandle => $filter) {
            if (
                $filterHandle === $handle
                || $filterHandle === $handleWithoutPrefix
            ) {
                return $filter;
            }
        }

        return null;
    }
}
