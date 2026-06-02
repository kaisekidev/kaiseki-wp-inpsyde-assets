<?php

declare(strict_types=1);

namespace Kaiseki\WordPress\InpsydeAssets\AssetFilter;

use Inpsyde\Assets\Style;

interface StyleFilterInterface
{
    public function __invoke(Style $asset): ?Style;
}
