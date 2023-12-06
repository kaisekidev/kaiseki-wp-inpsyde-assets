<?php

declare(strict_types=1);

namespace Kaiseki\WordPress\InpsydeAssets\Interface;

use Inpsyde\Assets\Asset;

interface AssetFilterInterface
{
    public function __invoke(Asset $asset): ?Asset;
}
