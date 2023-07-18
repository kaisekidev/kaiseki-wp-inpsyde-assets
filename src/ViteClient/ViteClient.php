<?php

declare(strict_types=1);

namespace Kaiseki\WordPress\InpsydeAssets\ViteClient;

use Kaiseki\WordPress\Environment\EnvironmentInterface;
use Kaiseki\WordPress\Hook\HookCallbackProviderInterface;

use function Env\env;
use function function_exists;
use function is_array;
use function is_bool;

final class ViteClient implements HookCallbackProviderInterface
{
    private const VITE_CLIENT = '@vite/client';

    private ?bool $isViteClientActive = null;

    public function __construct(
        private readonly EnvironmentInterface $environment,
        private readonly string $host = 'localhost',
        private readonly int $port = 5173,
    ) {
    }

    public function registerHookCallbacks(): void
    {
        add_action('wp_head', [$this, 'renderViteClientScript']);
        add_action('admin_head', [$this, 'renderViteClientScript']);
    }

    public function renderViteClientScript(): void
    {
        if (!self::isHot() || (is_admin() && !$this->isBlockEditor())) {
            return;
        }

        echo \Safe\sprintf(
            '<script type="module" src="%s%s"></script>',
            trailingslashit($this->getServerUrl()),
            self::VITE_CLIENT
        );
    }

    public function getServerUrl(): string
    {
        return \Safe\sprintf(
            'http://%s:%s/',
            env('VITE_HOST') !== null ? env('VITE_HOST') : $this->host,
            env('VITE_PORT') !== null ? env('VITE_PORT') : $this->port,
        );
    }

    public function isHot(): bool
    {
        if (!$this->environment->isLocal() && !$this->environment->isDevelopment()) {
            return false;
        }
        if (is_bool($this->isViteClientActive)) {
            return $this->isViteClientActive;
        }
        $url = trailingslashit(self::getServerUrl()) . self::VITE_CLIENT;
        return $this->isViteClientActive = $this->checkUrlWithCurl($url);
    }

    private function isBlockEditor(): bool
    {
        if (!is_admin() || !function_exists('get_current_screen')) {
            return false;
        }

        return (bool)get_current_screen()?->is_block_editor();
    }

    private function checkUrlWithCurl($url): bool
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $httpCode === 200;
    }
}
