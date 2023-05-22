<?php

declare(strict_types=1);

namespace Kaiseki\WordPress\InpsydeAssets\Loader;

use Inpsyde\Assets\Asset;
use Inpsyde\Assets\BaseAsset;
use Inpsyde\Assets\Loader\AbstractWebpackLoader;

use Inpsyde\Assets\Script;
use Inpsyde\Assets\Style;
use function dirname;
use function is_array;
use function pathinfo;
use function str_starts_with;

use const PATHINFO_FILENAME;

/**
 * Implementation of Vite manifest.json parsing into Assets.
 *
 * @link https://vitejs.dev/guide/backend-integration.html
 */
class ViteManifestLoader extends AbstractWebpackLoader
{
    /**
     * @var string
     */
    protected $handlePrefix = '';

    /**
     * {@inheritDoc}
     *
     * @param array<string, mixed>  $data
     * @param string $resource
     *
     * @return list<Asset>
     */
    protected function parseData(array $data, string $resource): array
    {
        $directory = trailingslashit(dirname($resource));
        $assets = [];
        foreach ($data as $handle => $entry) {
            // It can be possible, that the "handle"-key is a filepath.
            $handle = pathinfo($handle, PATHINFO_FILENAME);

            if (str_starts_with($handle, '_')) {
                continue;
            }

            $file = is_array($entry) && isset($entry['file']) ? $entry['file'] : null;
            if ($file === null) {
                continue;
            }
            $sanitizedFile = $this->sanitizeFileName($file);
            $fileUrl = $this->directoryUrl . $sanitizedFile;
            $filePath = $directory . $sanitizedFile;
            $asset = $this->buildAsset($this->prefixHandle($handle), $fileUrl, $filePath);

            if ($asset === null) {
                continue;
            }

            $assets[] = $asset;
        }

        return $assets;
    }

    /**
     * @param string $handlePrefix optional prefix to be added to all asset handles
     *
     * @return static
     */
    public function withHandlePrefix(string $handlePrefix): ViteManifestLoader
    {
        $this->handlePrefix = $handlePrefix;

        return $this;
    }

    /**
     * @param string $handle
     * @param string $fileUrl
     * @param string $filePath
     *
     * @return Asset|null
     */
    protected function buildAsset(string $handle, string $fileUrl, string $filePath): ?Asset
    {
        $extensionsToClass = [
            'css' => Style::class,
            'scss' => Style::class,
            'js' => Script::class,
            'ts' => Script::class,
        ];

        /** @var array{filename?:string, extension?:string} $pathInfo */
        $pathInfo = pathinfo($filePath);
        $filename = $pathInfo['filename'] ?? '';
        $extension = $pathInfo['extension'] ?? '';

        if (!in_array($extension, array_keys($extensionsToClass), true)) {
            return null;
        }

        $class = $extensionsToClass[$extension];

        /** @var Asset|BaseAsset $asset */
        $asset = new $class($handle, $fileUrl, $this->resolveLocation($filename));
        $asset->withFilePath($filePath);
        $asset->canEnqueue(true);

        if ($asset instanceof BaseAsset) {
            $this->autodiscoverVersion
                ? $asset->enableAutodiscoverVersion()
                : $asset->disableAutodiscoverVersion();
        }

        return $asset;
    }

    /**
     * @param string $handle
     *
     * @return string
     */
    private function prefixHandle(string $handle): string
    {
        return $this->handlePrefix . $handle;
    }
}
