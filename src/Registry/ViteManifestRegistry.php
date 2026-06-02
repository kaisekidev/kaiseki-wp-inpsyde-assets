<?php

declare(strict_types=1);

namespace Kaiseki\WordPress\InpsydeAssets\Registry;

use Closure;
use Inpsyde\Assets\Asset;
use Inpsyde\Assets\AssetManager;
use Inpsyde\Assets\Script;
use Inpsyde\Assets\Style;
use Kaiseki\WordPress\Hook\HookProviderInterface;
use Kaiseki\WordPress\InpsydeAssets\Loader\ViteManifestLoader;
use Kaiseki\WordPress\InpsydeAssets\OutputFilter\ModuleTypeScriptOutputFilter;
use Kaiseki\WordPress\InpsydeAssets\ViteClient\ViteClient;

use function add_action;
use function count;
use function is_callable;
use function is_string;
use function preg_quote;
use function Safe\preg_replace;

/**
 * @phpstan-type AssetFilterCallable callable(Asset $asset, ViteClient $viteClient, string $handle): Asset
 * @phpstan-type ScriptFilterCallable callable(Script $script, ViteClient $viteClient, string $handle): Script
 * @phpstan-type StyleFilterCallable callable(Style $style, ViteClient $viteClient, string $handle): Style
 * @phpstan-type ViteManifestCallback callable(ViteClient $viteClient): string|null
 * @phpstan-type DirectoryUrlCallback callable(ViteClient $viteClient): string
 */
class ViteManifestRegistry implements HookProviderInterface
{
    private ?Closure $scriptFilter;
    private ?Closure $styleFilter;

    /**
     * @param ViteManifestLoader           $loader
     * @param ModuleTypeScriptOutputFilter $esModuleFilter
     * @param ViteClient                   $viteClient
     * @param array<array-key, mixed>      $viteManifests  list<string|ViteManifestCallback|null>
     * @param ScriptFilterCallable|null    $scriptFilter
     * @param array<array-key, mixed>      $scriptFilters  map of handle => ScriptFilterCallable|bool
     * @param StyleFilterCallable|null     $styleFilter
     * @param array<array-key, mixed>      $styleFilters   map of handle => StyleFilterCallable|bool
     * @param bool                         $autoload
     * @param mixed                        $directoryUrl
     * @param string                       $handlePrefix
     * @param bool                         $esModules
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

        if (is_callable($directoryUrl)) {
            $directoryUrl = $directoryUrl($this->viteClient);
        }
        if (is_string($directoryUrl) && $directoryUrl !== '') {
            $this->loader->withDirectoryUrl($directoryUrl);
        }

        if ($handlePrefix === '') {
            return;
        }

        $this->loader->withHandlePrefix($handlePrefix);
    }

    public function addHooks(): void
    {
        add_action(AssetManager::ACTION_SETUP, [$this, 'registerAssets']);
    }

    /**
     * Hook callback to register assets.
     *
     * @param AssetManager $assetManager
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

            if (!is_string($viteManifest) || $viteManifest === '') {
                continue;
            }

            foreach ($this->loader->load($viteManifest) as $asset) {
                if ($asset instanceof Asset) {
                    $assets[] = $asset;
                }
            }
        }

        return $assets;
    }

    /**
     * @param list<Asset> $assets
     *
     * @return list<Asset>
     */
    protected function filterAssets(array $assets): array
    {
        $filtered = [];
        foreach ($assets as $asset) {
            $filteredAsset = $this->filterAsset($asset);
            if ($filteredAsset !== null) {
                $filtered[] = $filteredAsset;
            }
        }

        return $filtered;
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
            $filtered = $typeFilter($asset, $this->viteClient, $handle);
            if (!$filtered instanceof Asset) {
                return null;
            }
            $asset = $filtered;
        }

        if ($isScript && $this->esModules && $asset instanceof Script) {
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

            return null;
        }

        $filtered = $assetFilter($asset, $this->viteClient, $handle);

        return $filtered instanceof Asset ? $filtered : null;
    }

    /**
     * Get filter by handle.
     *
     * @param string                  $handle
     * @param array<array-key, mixed> $filters map of handle => ScriptFilterCallable|StyleFilterCallable|bool
     *
     * @return mixed ScriptFilterCallable|StyleFilterCallable|bool|null
     */
    private function getFilter(string $handle, array $filters): mixed
    {
        $handleWithoutPrefix = preg_replace(
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
