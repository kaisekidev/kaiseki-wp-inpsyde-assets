<?php

declare(strict_types=1);

namespace Kaiseki\WordPress\InpsydeAssets\AssetFilter;

use Inpsyde\Assets\Asset;

interface AssetFilterInterface
{
    public function __invoke(Asset $asset): ?Asset;
}
