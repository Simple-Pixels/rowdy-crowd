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
| Store finder *(plugin)* | Stockist locator replacing the static Shopify find-a-store page (added Nov 2026, not in the original estimate). |

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

### Currency and the switchers (spec, Nov 2026)

Built in `modules/currency/` plus the region switcher in `modules/geolocation/`. See their READMEs.

- Pricing is done by **WooPayments Multi-Currency** (set up by the client). Enabled currencies: **AUD (store currency), EUR, NZD, GBP, USD**. Switching works through WooPayments' `?currency=XXX` query var; we rely on that rather than its internals. The only code that calls WooPayments is `Currencies` in the currency module.
- **`[region_switcher]` also switches currency**: Australia → AUD, United States → USD (`Region::currencies()`, filter `rowdy_crowd_region_currencies`). The currency is added to the region links and the first-visit geo redirect.
- **`[currency_switcher]`** is for the footer and lists every currency enabled in WooPayments. It doesn't change region.
- Both switchers use the **shared `Dropdown`** (`includes/class-dropdown.php`, root `assets/dropdown.*`): **no flags, transparent background, white border**. Reuse it for any new dropdown rather than writing a new one.
- The Hello World placeholder module was removed in 0.3.0.
- Open question: first-time visitors who haven't chosen a currency. The plan is to rely on WooPayments' "automatically switch currency based on location" setting. Cached pages may also show a stale currency label. Both need testing on live.

### Store finder (spec, Nov 2026)

Built in `modules/store-finder/`. See its README. It replaces the static find-a-store list on the old Shopify site (about 70 Australian stockists, grouped by state; each has a store name, street address, suburb, state, postcode, optional phone and optional website). It was based on the client's `Simple-Pixels/simple-maps` component, folded into this plugin.

- **`rc_stockist` post type** (not public: no pages of its own; `rc_` matches the site's existing `rc_faq`). Title = store name.
- **ACF fields defined in code and saved to ACF's database** (field group "Stockist details", saved on the first administrator wp-admin load, updated in place when `Stockist_Fields::VERSION` is bumped). They must be in the database because **WP All Import's ACF add-on can't see PHP-only field groups**; the client imports stockists with it. The Google Map field gives the pin, and the suburb, state and postcode when an address is picked from Google's search. Other fields: optional street-address override, **Suburb, State and Postcode** (added in 0.5.1: the first WP All Import saved only a street and a pin, so every store had an empty state, no chips and no postcode search), phone, website, and a region (Australia or United States). Suburb/state/postcode precedence: the stockist's own fields, then the map's parts, then parsed from the map's address text. **If you change the field definitions, bump `Stockist_Fields::VERSION`** (now 2).
- Existing stockists: the 66 on the old Shopify page were prepared as a CSV (`stockist-import/`, git-ignored, pins from OpenStreetMap). The finder reads suburb, state and postcode from the Google Map value's parts, so an import must write them (or the finder must fall back to parsing the address).
- **Region**: each stockist belongs to a region and `[store_finder]` shows the current site's stores by default (`region="all"` overrides). There is no US stockist data yet.
- **Finder**: server-rendered list plus Google Map. Works without JS; JS adds search, **state chips** (a dropdown when there are more than 12 states), **"Stores near me"** (browser geolocation, nearest first with distances; the location never leaves the browser) and the map. **Clicking anywhere on a store's card** selects it (there is no "Show on map" button; the name is a real button underneath for keyboards). Nothing-matches shows a card in the list's place so the layout doesn't shift.
- **Colours (brand palette)**: link and focus colour is blue `#316195`; pink `#E93CAC`, teal `#00BBB4`, orange `#FF6418` and lime `#B7BF10` are used as fills/tints only with near-black text `#1F1B19`, because they fail 4.5:1 as text on white. Don't make the finder all one colour again (the client said it was "too pink"). **Exceptions chosen by the client (white text on a brand colour):** the "Stores near me" button (orange, 2.96:1) and the chosen state chip (pink 3.68, orange 2.96, teal 2.40, lime 2.01). All below AA, lime worst. Deeper shades would pass; offer them if it comes up. The chosen chip has no tick, and messages from near-me are a toast at the bottom centre (moved to `<body>` by the script so a transformed Elementor section can't break `position: fixed`), with the store count at the right of the chip row. Default `height` is 720px.
- **Design rule from the client: never use a coloured stripe or rounded border down one side of a box** (cards, toasts, notices, callouts). They called it ugly. Use a full border, a tint, or an even ring instead. 0.5.3 removed the ones in the finder.
- **Info window**: `headerDisabled: true` plus our own close button. Google's header row left a 48px gap above the text. Padding was measured on real Google Maps on the live site.
- **Google Maps**: key (and optional Map ID, pin colour) under Stockists → Settings, or the `ROWDY_CROWD_GOOGLE_MAPS_API_KEY` constant. Needs the Maps JavaScript and Places APIs. Advanced markers are used because `google.maps.Marker` is deprecated.
- Not built yet: a bulk import for the existing stockists (the CSV exists; the client is trying WP All Import), and searching by typed postcode/suburb for distance (text search matches only).

### Homepage (approved wireframe)

Top bar (geolocation/shipping notice), header nav (Shop Our Range, Custom Printing, For Businesses, Event Categories, Our Story, Shop Online CTA), hero ("SUSTAIN-able"), product category tiles (Wine Tumblers, Champagne Flutes, Gin Balloons, Beer Schooners, Accessories), "Make it your own" and "Business and wholesale" panels, USP icons (dishwasher safe, 100% recyclable, unbreakable, reusable), video plus "three easy steps" custom-order explainer, press logos, event category tiles (Wineries/Distilleries/Venues, Community and Corporate Events, Weddings, Party and Catering Packs, Family Packs and Gifting), reviews carousel, About section, featured bundles carousel, "reUSE-able" banner, newsroom, #gettingrowdycrowd social feed, newsletter signup, footer.

## Plugin architecture

```
rowdy-crowd.php           Plugin header, global constants, bootstrap
includes/                 Core (RowdyCrowd\Core): Autoloader, Plugin, abstract Module, Dropdown
assets/                   Shared front-end files used by core classes (dropdown CSS/JS)
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

- **Never push directly to `main`.** Always work on a new branch, push it, and open a PR into `main`. The one approved exception is the `Simple-Pixels/kinsta-deploy-setup` tool, which commits `.github/workflows/deploy.yml` straight to `main` when it is (re)run.
- **Do not add "Co-Authored-By: Claude" or any Claude/AI attribution** to commit messages or PR descriptions.
- Branch names: `feature/<short-description>`, `fix/<short-description>`.

## Deployment

- **Every push to `main` (i.e. every merged PR) deploys to the live Kinsta site** through GitHub Actions (`.github/workflows/deploy.yml`). It can also be run manually from the Actions tab ("Run workflow").
- The workflow rsyncs the repo to `/www/rowdycrowd_579/public/wp-content/plugins/rowdy-crowd/` with `--delete`. Anything in that folder that isn't in the repo is removed, so never edit plugin files on the server.
- Development-only files (`CLAUDE.md`, `README.md` files, `.git*`, `.github`, `.claude`) are excluded from the deploy. Add new dev-only files to the `--exclude` list so they don't become publicly readable.
- Connection details live in the repo's Actions secrets: `KINSTA_SSH_HOST`, `KINSTA_SSH_PORT`, `KINSTA_SSH_USER`, `KINSTA_SSH_KEY`, `KINSTA_DEPLOY_PATH`. The deploy key (no passphrase) is `~/.ssh/kinsta-deploy-Simple-Pixels-rowdy-crowd` on the machine that ran the setup.
- Set up with `Simple-Pixels/kinsta-deploy-setup`. It needed local fixes (Kinsta company ID, an SSH key passphrase bug under PowerShell 7, and a wrong guessed path) that aren't in that repo yet.
- Live is a production site, so test risky changes on staging first.

## Environment notes

- **PHP 8.4 is available through Laravel Herd**: `/c/Users/zacmo/.config/herd/bin/php84/php.exe` (it isn't on the bash PATH as `php`). **Always run `php -l` on every changed PHP file before pushing**, because merging to `main` deploys to live. WordPress isn't installed locally. To go beyond a syntax check, boot the plugin in a throwaway script with stubbed WordPress and WooPayments functions (keep it outside the repo) and print what the shortcodes return.
- Hosting is **Kinsta** (server page cache, IP geolocation via MyKinsta → Tools). Assume pages are cached. Per-visitor logic must not depend on PHP running for every page view; use the domain, an uncached REST endpoint, or client-side JS.
- The site is built with **Elementor and Elementor Pro**. Custom classes go in Advanced → CSS Classes; the editor preview loads with `?elementor-preview`.
- **Elementor kit styles every `<button>` on hover and focus** (`.elementor-kit-8 button:hover, button:focus`: accent-coloured background and `border-style: none`; Hello Elementor's `reset.css` adds a pink background too). Any custom button must repeat its border, border-radius and background in its `:hover`, `:focus` and `:active` rules, or it will change shape when touched. Check new UI on the live site (https://rowdycrowd.kinsta.cloud) before calling it done.
- Other plugins on live (Oct 2026): WooCommerce, WooCommerce Payments, ACF Pro, WP Rocket, Rank Math, WP Offload Media (amazon-s3-and-cloudfront-pro), Imagify, Members, WP Mail SMTP, MonsterInsights, Duplicate Post, Disable Comments, Classic Editor, Migrate Guru, WP Pusher.
- **WP Rocket** adds another page cache. Clear it after deploys that change front-end output. The same "assume pages are cached" rule applies. **It also keeps minified copies of scripts and styles in `wp-content/cache/min` and can serve a stale one**: the store finder went live with new HTML and CSS but an old script for logged-out visitors, while the logged-in admin saw the real files. `includes/class-wp-rocket.php` excludes this plugin's own assets (`rocket_exclude_js` / `rocket_exclude_css`, checked against WP Rocket's source). **Always check new front-end work as a logged-out visitor** (private window, or the browser pane): fetch the script URL and confirm it contains the new code. Sites behind Cloudflare also cache for a year, so asset URLs must change with the version.
- **Testing focus and hover states:** in the browser pane `document.hasFocus()` is false, so scripted `.focus()` never applies `:focus` styles and a "style unchanged on focus" check passes without proving anything. Instead rewrite the stylesheet so `:hover`, `:focus`, `:active` and `:focus-visible` become a class (`.rc-force`), force the theme's own rules the same way, add the class, and compare computed styles. Always include an unprotected `<button>` as a control that must change. Real Hello Elementor rules on a focused input: `input[type=search]:focus { border-color: #333 }` and `input:focus-visible { outline: #000 solid 2px; outline-offset: 2px; box-shadow: #fff 0 0 0 4px }`; and `button { white-space: nowrap }`.
- **WP Pusher** is installed but must not also deploy this repo; GitHub Actions is the only deploy path.
