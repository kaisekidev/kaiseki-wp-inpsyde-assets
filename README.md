# kaiseki/wp-inpsyde-assets

Companion module for [`inpsyde/assets`](https://github.com/inpsyde/assets) that registers a Vite
build's `manifest.json` entries as WordPress assets and wires up a Vite dev-server client.

It provides:

- **`ViteManifestLoader`** — an `inpsyde/assets` webpack-style loader that parses a Vite
  `manifest.json` into `Script`/`Style` assets (by extension), with optional handle prefixing and a
  configurable directory URL.
- **`ViteManifestRegistry`** — a `kaiseki/wp-hook` `HookProviderInterface` that, on the
  `inpsyde/assets` setup action, loads the configured manifests, applies per-handle script/style
  filters, optionally tags scripts as ES modules, and registers them with the `AssetManager`.
- **`ViteClient`** — injects the `@vite/client` dev script into the page when the Vite dev server is
  running (detected on local/development environments via `kaiseki/wp-env`).

## Installation

```bash
composer require kaiseki/wp-inpsyde-assets
```

Requires PHP 8.2 or newer.

## Usage

Register `ConfigProvider` with your laminas-style config aggregator and activate the providers via
`kaiseki/wp-hook`. Configure the manifests and the dev-server client under their config keys:

```php
use Kaiseki\WordPress\InpsydeAssets\Registry\ViteManifestRegistry;
use Kaiseki\WordPress\InpsydeAssets\ViteClient\ViteClient;

return [
    'vite_client' => [
        'host' => 'localhost',
        'port' => 5173,
    ],
    'vite_manifest' => [
        // list of manifest.json paths, or callables receiving the ViteClient
        'files'         => [get_theme_file_path('dist/manifest.json')],
        'autoload'      => true,
        'handle_prefix' => 'theme-',
        'es_modules'    => true,
        // optional per-handle filters: handle => callable|bool
        'scripts'       => [],
        'styles'        => [],
    ],
    'hook' => [
        'provider' => [
            ViteClient::class,
            ViteManifestRegistry::class,
        ],
    ],
];
```

`VITE_HOST` and `VITE_PORT` environment variables override the configured dev-server host/port.

## Development

```bash
composer install
composer check   # check-deps, cs-check, phpstan
```

## License

MIT — see [LICENSE](LICENSE).
