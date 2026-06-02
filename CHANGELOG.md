# Changelog

All notable changes to this project will be documented in this file, in reverse chronological order by release.

## 1.0.0 - 2026-06-02

First tagged release.

### Added

- `ViteManifestLoader`, `ViteManifestRegistry` (+ factory and `ConfigProvider`), `ViteClient` (+
  factory), the `ModuleTypeScriptOutputFilter`, and the `AssetFilter` filter interfaces — register a
  Vite `manifest.json` as `inpsyde/assets` Script/Style assets and inject the Vite dev-server client.

### Changed

- PHP requirement is `^8.2` (PHP 8.4 is the primary target).
- Adopted the `kaiseki/wp-hook` 2.0 provider API: `ViteManifestRegistry` and `ViteClient` implement
  `HookProviderInterface` with `addHooks()` (was `HookCallbackProviderInterface::registerHookCallbacks()`).
- `kaiseki/config` and `kaiseki/wp-hook` pinned to `^2.0`, `kaiseki/wp-env` to `^1.0`; the factories use
  the config 2.0 `Config::fromContainer()` entry point (was `Config::get()`).
- Converted the toolchain from PHP_CodeSniffer to the shared `kaiseki/php-coding-standard` (php-cs-fixer)
  standard; modernized the dev stack (PHPStan 2, PHPUnit 11 schema, composer-require-checker 4); dropped
  `squizlabs/php_codesniffer`, the direct `friendsofphp/php-cs-fixer`, and the bespoke scripts; declared
  `thecodingmachine/safe` (used directly) in `require`. CI now runs via the reusable workflow in
  `kaisekidev/.github`.
- The `AssetFilter` interfaces moved from the non-autoloadable `…\Interface` namespace to
  `Kaiseki\WordPress\InpsydeAssets\AssetFilter`, matching their directory (PSR-4).

### Fixed

- `ViteManifestRegistryFactory` referenced a non-existent `Config::callable()` and carried a dead,
  return-less `getDirectoryUrl()` method — both removed; filters are read via `Config::get()` and
  validated with `is_callable()`.
- `ViteClient::isHot()` called the instance method `getServerUrl()` statically; now uses `$this->`.
- The Vite dev-server reachability check moved off raw cURL to `wp_remote_get()` with a 1s timeout — it
  fails gracefully (no exception) when the dev server is down, the normal production case.
- PHPStan 2 (level max): removed several inline `@var` overrides in favour of honest config-shaped
  (`array<array-key, mixed>` / `mixed`) parameters narrowed at the point of use (`is_string`,
  `instanceof Asset`/`Script`), with no suppressions.
