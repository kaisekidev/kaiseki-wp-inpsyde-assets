<?php

declare(strict_types=1);

namespace Kaiseki\WordPress\InpsydeAssets\AssetFilter;

use Inpsyde\Assets\Script;

interface ScriptFilterInterface
{
    public function __invoke(Script $asset): ?Script;
}
