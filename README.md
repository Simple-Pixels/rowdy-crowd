# Rowdy Crowd

> **Site-specific** · One plugin holding all custom functionality for the Rowdy Crowd WooCommerce site (AU + US from one install), with each feature in its own module.

| | |
| --- | --- |
| Type | Site-specific (Rowdy Crowd, `rowdycrowd.com.au` / `rowdycrowd.com`) |
| Version | 0.5.3 (`ROWDY_CROWD_VERSION` + plugin header) |
| Main file | `rowdy-crowd.php` |
| Text domain | `rowdy-crowd` |
| Prefix | `RowdyCrowd\` (namespaces), `rowdy_crowd_` (hooks, options, meta), `ROWDY_CROWD_` (constants), `rowdy-crowd-` (handles, CSS) |
| Repository | `Simple-Pixels/rowdy-crowd` |

## What it does

Rowdy Crowd is moving from two Shopify stores (AU and US) to one WooCommerce site. This plugin holds the site's custom code. Planned modules include the custom-print product renderer and cart flow, quoting and tiered pricing. Project context and scope are in [CLAUDE.md](CLAUDE.md).

| Module | Status | What it does |
| --- | --- | --- |
| [geolocation](modules/geolocation/) | In progress | AU/US regions by domain, `aunz`/`usa` visibility classes, first-visit geo redirect, `[region_switcher]` (also switches currency). |
| [currency](modules/currency/) | In progress | `[currency_switcher]` for WooPayments Multi-Currency (AUD, EUR, NZD, GBP, USD). |
| [store-finder](modules/store-finder/) | In progress | Stockists post type and `[store_finder]`: searchable store list with a Google Map. |

Each module has its own README with settings, hooks and files.

## Requirements

| Requirement | Version / notes |
| --- | --- |
| WordPress | 6.4+ |
| PHP | 8.1+ |
| Required plugins | None for the core. WooCommerce-dependent modules gate themselves with `is_enabled()`. |
| Optional plugins | WooCommerce, Elementor / Elementor Pro (the site's page builder), WooPayments with Multi-Currency (needed for the currency switcher and region → currency switching), Advanced Custom Fields (needed for the store finder, which also needs a Google Maps API key). |
| Hosting | **Kinsta**: full-page cache and IP geolocation (MyKinsta → Tools → Geolocation). Both domains on the same site. |

## Installation

1. Deploys are automatic: every merge into `main` runs `.github/workflows/deploy.yml`, which rsyncs the repo to `wp-content/plugins/rowdy-crowd/` on the live Kinsta site. It can also be run manually from the Actions tab. Dev-only files (`CLAUDE.md`, READMEs, `.git*`) are excluded.
2. Activate **Rowdy Crowd** (first install only).
3. The plugin has no visible output on its own. Check the version shown on the Plugins screen matches the Changelog below.
4. Follow each module's README for its setup (e.g. [geolocation hosting setup](modules/geolocation/README.md#hosting-setup-kinsta)).

## Configuration

There is no plugin-wide settings page. Configuration is per module (see each module README) and through `wp-config.php` constants such as `ROWDY_CROWD_TEST_COUNTRY` (geolocation testing).

## Usage

| Shortcode | Module |
| --- | --- |
| `[region_switcher]` | geolocation (switches region and currency) |
| `[currency_switcher]` | currency |
| `[store_finder region="current" height="560px"]` | store-finder |

Both render the shared dropdown: transparent background, white border, no flags.

CSS classes `aunz` / `usa` on any element (Elementor: Advanced → CSS Classes) limit it to one region.

## How it works

### Data model

Core: none. Modules document their own (e.g. geolocation uses the `rowdy_crowd_region` cookie and no database storage).

### Request flow

1. `rowdy-crowd.php` defines the constants, registers the autoloader and boots `RowdyCrowd\Core\Plugin` on `plugins_loaded`.
2. `Plugin` instantiates every class in `Plugin::MODULES` (filterable with `rowdy_crowd_modules`) and calls `register()` on those whose `is_enabled()` returns true.
3. `rowdy_crowd_loaded` fires. Get a loaded module with `RowdyCrowd\Core\Plugin::instance()->get_module( 'module-id' )`.

Autoloading follows WordPress file naming: `RowdyCrowd\Core\Foo_Bar` → `includes/class-foo-bar.php`; `RowdyCrowd\Modules\Some_Module\Foo_Bar` → `modules/some-module/class-foo-bar.php`. Details: [includes/README.md](includes/README.md) and [modules/README.md](modules/README.md).

### Endpoints

| Endpoint | Module |
| --- | --- |
| `GET /wp-json/rowdy-crowd/v1/region` | geolocation (uncached visitor-region lookup) |

### Front end

Core loads the shared dropdown CSS/JS from `assets/` (used by both switchers). Modules enqueue their own assets (e.g. geolocation prints an inline redirect script).

## File structure

```
rowdy-crowd.php              Plugin header, global constants, bootstrap
includes/                    Core (RowdyCrowd\Core): Autoloader, Plugin, abstract Module   → includes/README.md
modules/                     One folder per feature                                      → modules/README.md
  geolocation/               AU/US regions, redirect, switcher                            → modules/geolocation/README.md
  currency/                  [currency_switcher] for WooPayments Multi-Currency           → modules/currency/README.md
  store-finder/              Stockists post type, [store_finder], Google Maps settings    → modules/store-finder/README.md
assets/                      Shared front-end files (dropdown CSS/JS)
.github/workflows/deploy.yml GitHub Actions deploy to Kinsta (on push to main)
CLAUDE.md                    Project context, coding standards, git workflow
```

## Hooks

| Hook | Type | Purpose |
| --- | --- | --- |
| `rowdy_crowd_modules` | filter | Module class names before they load. |
| `rowdy_crowd_loaded` | action | After all modules are registered. Receives the `Plugin` instance. |

Module hooks are listed in each module's README (e.g. `rowdy_crowd_region`, `rowdy_crowd_region_domains`).

## Gotchas

- **Pages are cached by Kinsta.** Per-visitor logic can't rely on PHP running each page view. Use the domain, an uncached REST endpoint, or client-side JS (the geolocation module is the pattern to follow).
- **Two domains, one install.** Carts and logins don't carry across `.com` and `.com.au`. Content saved with absolute URLs keeps the domain it was created on.
- **Every new module must be registered** in `Plugin::MODULES` and listed in `modules/README.md`, or it won't load.
- **WooCommerce-dependent modules must return false from `is_enabled()`** when Woo isn't active, so the plugin never fatals.
- **Lint PHP before pushing: merging to `main` deploys to live.** PHP 8.4 comes with Laravel Herd (`~/.config/herd/bin/php84/php.exe -l <file>`). WordPress isn't installed locally, so test behaviour on staging.
- **Merging into `main` deploys to live**, and the deploy uses `rsync --delete`. Never edit plugin files on the server, and add any new dev-only files to the workflow's `--exclude` list.
- **WP Rocket also caches pages, and can serve stale scripts.** Clear it after deploys that change front-end output. The plugin's own scripts and styles are excluded from its minify cache, but the first deploy with that change needs one clear. Check changes as a logged-out visitor, not as an admin. See [modules/store-finder/README.md](modules/store-finder/README.md#gotchas).
- **Currency is per visitor, pages are cached.** The currency switcher's label is rendered on the server, so test it with caching on (Kinsta and WP Rocket). See [modules/currency/README.md](modules/currency/README.md#caching).

## Development

- Project context, coding standards and git workflow: [CLAUDE.md](CLAUDE.md).
- Versioning: bump `ROWDY_CROWD_VERSION` and the plugin header together.
- New feature: add a module (see [modules/README.md](modules/README.md)) with its own README, and update the module table here.
- Testing: deploy to staging; for geolocation use `ROWDY_CROWD_TEST_COUNTRY` in `wp-config.php`.

## Changelog

| Version | Notes |
| --- | --- |
| 0.5.3 | Store finder: removed every one-sided coloured border (the toast, the admin notice, and the stripe on the chosen card, which now has a tint and an even ring). |
| 0.5.2 | Store finder: the chosen state chip is a plain fill with white text (no tick), the store count sits at the right of the chip row, and the "Stores near me" messages are a toast at the bottom centre of the screen. |
| 0.5.1 | Store finder: Suburb, State and Postcode fields (imported stores had none, so no chips and no postcode search), "Stores near me" in white on orange, a taller finder, no sideways scrolling in the list, a single focus ring on the search, and the popup close button no longer clipped. Plugin assets are excluded from the WP Rocket minify cache so visitors cannot be served a stale script. |
| 0.5.0 | Store finder: state chips, "Stores near me", clickable store cards (the "Show on map" button is gone), a tighter info window, brand colours beyond pink, and a stable layout when no stores match. |
| 0.4.1 | Stockist fields are saved to ACF's database (ACF → Field Groups) so WP All Import can see them. |
| 0.4.0 | Store finder module: Stockists post type, Google Maps settings and `[store_finder]` (searchable list, state filter, map). |
| 0.3.1 | Fix switcher shrinking on hover/focus (the Elementor kit removed its border). Smaller, lighter text. |
| 0.3.0 | Currency module with `[currency_switcher]`. `[region_switcher]` now also switches currency (AUD/USD). Both switchers share one dropdown: no flags, transparent background, white border. Hello World module removed. |
| 0.2.0 | Geolocation module (AU/US regions, redirect, switcher). |
| 0.1.0 | Base plugin: autoloader, module system, hello-world module. |
