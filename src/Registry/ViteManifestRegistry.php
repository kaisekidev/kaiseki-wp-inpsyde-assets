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

use function add_action;
use function array_merge;
use function array_reduce;
use function array_unshift;
use function array_values;
use function count;
use function is_callable;
use function ltrim;

/**
 * @phpstan-type AssetFilterCallable callable(Asset $asset, string $handle): Asset
 * @phpstan-type ScriptFilterCallable callable(Script $script, string $handle): Script
 * @phpstan-type StyleFilterCallable callable(Style $style, string $handle): Style
 */
class ViteManifestRegistry implements HookCallbackProviderInterface
{
    private ?Closure $scriptFilter;
    private ?Closure $styleFilter;
    private string $directoryUrl;

    /**
     * @param ViteManifestLoader                       $loader
     * @param ModuleTypeScriptOutputFilter             $esModuleFilter
     * @param list<string>                             $viteManifests
     * @param callable|null                            $scriptFilter
     * @param array<string, ScriptFilterCallable|bool> $scriptFilters
     * @param callable|null                            $styleFilter
     * @param array<string, StyleFilterCallable|bool>  $styleFilters
     * @param bool                                     $autoload
     * @param string                                   $handlePrefix
     * @param bool                                     $esModules
     */
    public function __construct(
        private readonly ViteManifestLoader $loader = new ViteManifestLoader(),
        private readonly ModuleTypeScriptOutputFilter $esModuleFilter = new ModuleTypeScriptOutputFilter(),
        private array $viteManifests = [],
        ?callable $scriptFilter = null,
        private array $scriptFilters = [],
        ?callable $styleFilter = null,
        private array $styleFilters = [],
        private bool $autoload = true,
        string $directoryUrl = '',
        private string $handlePrefix = '',
        private bool $esModules = true,
    ) {
        $this->scriptFilter = is_callable($scriptFilter) ? $scriptFilter(...) : null;
        $this->styleFilter = is_callable($styleFilter) ? $styleFilter(...) : null;

        if ($directoryUrl !== '') {
            $this->loader->withDirectoryUrl($directoryUrl);
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
     * Add paths to vite manifest.json files to load assets from.
     *
     * @param string $viteManifest
     * @param string ...$viteManifests
     *
     * @return $this
     */
    public function addViteManifest(string $viteManifest, string ...$viteManifests): self
    {
        array_unshift($viteManifests, $viteManifest);

        $this->viteManifests = array_merge(
            $this->viteManifests,
            array_values($viteManifests)
        );

        return $this;
    }

    /**
     * Add script filters.
     *
     * @param array<string, ScriptFilterCallable> $scripts
     *
     * @return $this
     */
    public function addScriptFilters(array $scripts): self
    {
        $this->scriptFilters = array_merge($this->scriptFilters, $scripts);
        return $this;
    }

    /**
     * Add style filters.
     *
     * @param array<string, StyleFilterCallable> $styles
     *
     * @return $this
     */
    public function addStyleFilters(array $styles): self
    {
        $this->styleFilters = array_merge($this->styleFilters, $styles);
        return $this;
    }

    /**
     * Autoload all assets in vite manifests on setup.
     *
     * @param bool $autoload
     *
     * @return $this
     */
    public function withAutoload(bool $autoload): self
    {
        $this->autoload = $autoload;
        return $this;
    }

    /**
     * Set the directory url for assets.
     *
     * @param string $directoryUrl
     *
     * @return $this
     */
    public function withDirectoryUrl(string $directoryUrl): self
    {
        $this->directoryUrl = $directoryUrl;
        $this->loader->withDirectoryUrl($this->directoryUrl);
        return $this;
    }

    /**
     * Automatically add type="module" to script tags.
     *
     * @param bool $esModules
     *
     * @return $this
     */
    public function withEsModules(bool $esModules): self
    {
        $this->esModules = $esModules;
        return $this;
    }

    /**
     * Prefix all asset handles with the given string.
     *
     * @param string $prefix
     *
     * @return $this
     */
    public function withHandlePrefix(string $prefix): self
    {
        $this->handlePrefix = $prefix;
        $this->loader->withHandlePrefix($prefix);
        return $this;
    }

    /**
     * @return list<Asset>
     */
    protected function loadAssets(): array
    {
        $assets = [];
        foreach ($this->viteManifests as $viteManifest) {
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
        if ($typeFilter !== null) {
            $asset = $typeFilter($asset, $handle);
        }

        if ($isScript && $this->esModules) {
            $asset->withFilters($this->esModuleFilter);
        }

        $assetFilter = $this->getFilter(
            $handle,
            $isScript ? $this->scriptFilters : $this->styleFilters
        );

        if (!is_callable($assetFilter)) {
            return (
                    $this->autoload === true
                    && $assetFilter !== false
                )
                || (
                    $this->autoload === false
                    && $assetFilter === true
                )
                ? $asset
                : null;
        }

        return $assetFilter($asset, $handle);
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
        foreach ($filters as $filterHandle => $filter) {
            if (
                $filterHandle === $handle
                || $filterHandle === ltrim($handle, $this->handlePrefix)
            ) {
                return $filter;
            }
        }
        return null;
    }
}
