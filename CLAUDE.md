# CLAUDE.md

Project context and working rules for the Rowdy Crowd plugin. **Keep this file updated** whenever new information or specifications come up.

## Project

Rowdy Crowd (https://rowdycrowd.com.au/) makes and sells unbreakable, BPA-free, reusable Tritan drinkware: wine tumblers, champagne flutes, gin balloons, beer schooners, and accessories. They work from an on-farm warehouse near Wagga Wagga/Junee, NSW, and sell across Australia, with US expansion underway and Europe planned.

- About 90% of revenue is **wholesale and custom-print** orders (events, festivals, wineries, venues, corporate). Retail (weddings, celebrations, bulk personal orders) still matters.
- They are moving from **two separate Shopify sites (AU and US)** to **one WooCommerce site**, ideally on one domain.
- This repo is a **single custom WordPress plugin** that holds the site's custom functionality.

### Stakeholders

- **Lisa Morey**: technical lead, main point of contact, will manage site updates.
- **Fiona Hamilton**: founder and owner, gives final approval and sign-off.
- **Tiana**: head of marketing, consulted on marketing decisions.

### Goals and UX principles

- Automate the custom-print ordering workflow up to print approval. **Manual artwork approval by Rowdy Crowd staff is required before anything goes to the printer.**
- Ideal flow: customer selects product, uploads artwork, picks print colours, sets quantities and event date, reviews, pays, then staff approve the artwork and production starts.
- Customers should reach their goal in **three clicks or fewer** wherever possible.
- Must work well on **mobile**.
- Brand: fun, colourful, quirky, energetic, but easy to use and professional. Sustainability and storytelling should feature prominently. Adapt the existing brand; no rebrand.
- Build to scale for AU, US and future EU markets.
- SEO/AEO is handled internally by the client and is out of scope.

### Deal-breakers (v1)

Artwork upload, customer self-service ordering, wholesale account management, integration with existing systems (CIN7, Klaviyo), and the admin approval workflow.

## Scope of work

From the scoping doc and rough hours estimate (Oct 2026). Items marked *(plugin)* are expected to become modules in this plugin; others may be third-party plugins or configuration.

| Area | Notes |
| --- | --- |
| Standard WooCommerce build | Discovery, wireframes (homepage wireframe approved), Woo setup, product templates, responsive work, QA. |
| Self-service custom ordering *(plugin)* | Custom product renderer and cart flow: file upload handling, static mockup generation, customer preview UI, Woo integration, admin artwork approval dashboard, notification emails, email gating, order lead time. Zakeke was costed as a third-party alternative. |
| Wholesale portal | B2B King plugin: setup incl. reordering, user roles and permissions, frontend dynamic visibility rules, dashboard tweaks. |
| Wholesale account approval | Application forms plus an admin approval workflow. |
| Tiered pricing *(partly plugin)* | Tiered and wholesale pricing rules, AU/US/local pricing, shipping logic and thresholds, FedEx API. |
| Klaviyo | Integration, customer sync, form integration. |
| CIN7 | Inventory integration: product mapping, order management. |
| Geolocation *(plugin)* | AU/US detection, dynamic content, pricing integration, printer-based order notifications, CookieYes and GDPR compliance. |
| Quote generation *(plugin)* | Quote generation logic and WooCommerce integration. Keep a traditional enquiry pathway for customers who need help. |

### Geolocation and regions (spec, Oct 2026)

Built in `modules/geolocation/`. See its README for details.

- Two versions of the site, **AU and US**, with **one central editing point** (single WP install).
- Domains: **rowdycrowd.com.au = AU** (region key `aunz`), **rowdycrowd.com = US** (region key `usa`). The domain decides the region, which keeps Kinsta page caching correct.
- All elements are visible by default. Class **`.aunz`** = AU visitors only; class **`.usa`** = US visitors only.
- Default region is AU. Visitors geolocated in the **US, Canada or Mexico** go to the US site. Uses **Kinsta IP geolocation** (`$_SERVER['GEOIP_COUNTRY_CODE']`), looked up client-side through a REST endpoint because cached pages don't run PHP.
- Visitors can change region with the **`[region_switcher]`** shortcode (dropdown with flag). Their choice is stored per domain in the `rowdy_crowd_region` cookie and wins over geolocation. The shortcode name was specified by the client, so it isn't prefixed.
- Search bots are never geo-redirected.
- Known limitation: carts and logins don't carry across the two domains.
- Other modules needing the region (e.g. AU/US pricing) should call `RowdyCrowd\Modules\Geolocation\Region::current()`.

### Homepage (approved wireframe)

Top bar (geolocation/shipping notice), header nav (Shop Our Range, Custom Printing, For Businesses, Event Categories, Our Story, Shop Online CTA), hero ("SUSTAIN-able"), product category tiles (Wine Tumblers, Champagne Flutes, Gin Balloons, Beer Schooners, Accessories), "Make it your own" and "Business and wholesale" panels, USP icons (dishwasher safe, 100% recyclable, unbreakable, reusable), video plus "three easy steps" custom-order explainer, press logos, event category tiles (Wineries/Distilleries/Venues, Community and Corporate Events, Weddings, Party and Catering Packs, Family Packs and Gifting), reviews carousel, About section, featured bundles carousel, "reUSE-able" banner, newsroom, #gettingrowdycrowd social feed, newsletter signup, footer.

## Plugin architecture

```
rowdy-crowd.php           Plugin header, global constants, bootstrap
includes/                 Core (RowdyCrowd\Core): Autoloader, Plugin, abstract Module
modules/<module-id>/      One folder per feature (RowdyCrowd\Modules\<Module_Id>)
  class-module.php        Module entry class extending RowdyCrowd\Core\Module
  README.md               Required: what the module does, hooks, settings, files
```

- The **repository root is the plugin folder** (deploys to `wp-content/plugins/rowdy-crowd/`).
- Every component lives in its own module folder with its own `README.md`. When adding a module, register it in `Plugin::MODULES` and add it to the table in `modules/README.md`.
- **Share globals.** Use the `ROWDY_CROWD_*` constants, the base `Module` helpers (`path()`, `url()`), and shared core classes rather than duplicating logic in modules. If two modules need the same helper, move it into `includes/`.
- Autoloading: `RowdyCrowd\Core\Foo_Bar` maps to `includes/class-foo-bar.php`, and `RowdyCrowd\Modules\Some_Module\Foo_Bar` maps to `modules/some-module/class-foo-bar.php`.
- Hooks: `rowdy_crowd_modules` (filter), `rowdy_crowd_loaded` (action). Prefix all custom hooks, options, meta keys, shortcodes and handles with `rowdy_crowd_` / `rowdy-crowd-`.
- Gate WooCommerce-dependent modules with `is_enabled()` (e.g. `class_exists( 'WooCommerce' )`) so the plugin never fatals without Woo.

## Coding standards

- Follow the **WordPress Coding Standards** (PHP, JS, CSS): tabs, Yoda conditions, `array()` syntax, snake_case functions and variables, `Capitalised_Underscore` class names, `class-*.php` file names.
- Target **PHP 8.1+** and **WordPress 6.4+**. Use type declarations.
- Write clean, readable, well-structured code that is easy to update. Keep comments short and useful.
- Escape output late (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`), sanitise input early, and check nonces and capabilities on every form, AJAX and REST handler.
- Make all user-facing strings translatable with the `rowdy-crowd` text domain.
- **Keep external packages to a minimum.** Prefer WordPress and WooCommerce core APIs. Add a dependency only when it clearly saves a lot of work, and record it here.
- Each module's README must stay accurate as the module changes.

## Versioning and docs

- Bump `ROWDY_CROWD_VERSION` and the plugin header together, and add a line to the Changelog in `README.md`.
- Keep the root `README.md` (module table, shortcodes, file structure, gotchas) accurate alongside the module READMEs.

## Git workflow

- **Never push directly to `main`.** Always work on a new branch, push it, and open a PR into `main`.
- **Do not add "Co-Authored-By: Claude" or any Claude/AI attribution** to commit messages or PR descriptions.
- Branch names: `feature/<short-description>`, `fix/<short-description>`.

## Environment notes

- PHP isn't installed on the local dev machine, so code can't be linted or run locally. Test on the deployed site.
- Hosting is **Kinsta** (server page cache, IP geolocation via MyKinsta → Tools). Assume pages are cached. Per-visitor logic must not depend on PHP running for every page view; use the domain, an uncached REST endpoint, or client-side JS.
- Page builder not yet confirmed. The geolocation module assumes **Elementor** may be used (Advanced → CSS Classes; editor preview loads with `?elementor-preview`). Update this once confirmed.
