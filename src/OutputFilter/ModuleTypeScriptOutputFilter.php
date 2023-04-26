<?php

declare(strict_types=1);

namespace Kaiseki\WordPress\InpsydeAssets\OutputFilter;

use Inpsyde\Assets\Asset;
use Inpsyde\Assets\OutputFilter\AssetOutputFilter;
use Inpsyde\Assets\Script;

class ModuleTypeScriptOutputFilter implements AssetOutputFilter
{
    public function __invoke(string $html, Asset $asset): string
    {
        if (!$asset instanceof Script) {
            return $html;
        }
        if ((bool)\Safe\preg_match('/type=["\'][^"\']*["\']/', $html)) {
            return \Safe\preg_replace('/type=["\'][^"\']*["\']/', 'type="module"', $html);
        }
        return \Safe\preg_replace('/<script/', '<script type="module"', $html);
    }
}
