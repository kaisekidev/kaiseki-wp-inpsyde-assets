<?php

declare(strict_types=1);

namespace Kaiseki\WordPress\InpsydeAssets\ViteClient;

use Kaiseki\WordPress\Environment\EnvironmentInterface;
use Kaiseki\WordPress\Hook\HookProviderInterface;

use function add_action;
use function Env\env;
use function esc_url;
use function function_exists;
use function get_current_screen;
use function is_admin;
use function is_bool;
use function is_wp_error;
use function Safe\sprintf;
use function trailingslashit;
use function wp_remote_get;
use function wp_remote_retrieve_response_code;

final class ViteClient implements HookProviderInterface
{
    private const VITE_CLIENT = '@vite/client';

    private ?bool $isViteClientActive = null;

    public function __construct(
        private readonly EnvironmentInterface $environment,
        private readonly string $host = 'localhost',
        private readonly int $port = 5173,
    ) {
    }

    public function addHooks(): void
    {
        add_action('wp_head', [$this, 'renderViteClientScript']);
        add_action('admin_head', [$this, 'renderViteClientScript']);
    }

    public function renderViteClientScript(): void
    {
        if (!$this->isHot() || (is_admin() && !$this->isBlockEditor())) {
            return;
        }

        $src = esc_url(trailingslashit($this->getServerUrl()) . self::VITE_CLIENT);

        echo sprintf('<script type="module" src="%s"></script>', $src);
    }

    public function getServerUrl(): string
    {
        return sprintf(
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
        $url = trailingslashit($this->getServerUrl()) . self::VITE_CLIENT;

        return $this->isViteClientActive = $this->checkUrl($url);
    }

    private function isBlockEditor(): bool
    {
        if (!is_admin() || !function_exists('get_current_screen')) {
            return false;
        }

        return (bool)get_current_screen()?->is_block_editor();
    }

    private function checkUrl(string $url): bool
    {
        $response = wp_remote_get($url, ['timeout' => 1]);
        if (is_wp_error($response)) {
            return false;
        }

        return wp_remote_retrieve_response_code($response) === 200;
    }
}
