# Geolocation module

Runs the AU and US versions of the site from one WordPress install, so content is edited in one place.

| Domain | Region | Key |
| --- | --- | --- |
| rowdycrowd.com.au | Australia (default) | `aunz` |
| rowdycrowd.com | United States | `usa` |

## Region-specific content

Every element is visible by default. To limit an element to one region, add a CSS class. In Elementor this goes under **Advanced → CSS Classes**.

| Class | Visible on |
| --- | --- |
| `aunz` | AU only |
| `usa` | US only |
| *(none)* | Both |

The body gets a `rowdy-crowd-region-{aunz|usa}` class, and a small inline stylesheet hides the other region's elements. In the Elementor editor nothing is hidden. Elements are outlined instead: teal dashes for `aunz`, pink dashes for `usa`.

In PHP, use `RowdyCrowd\Modules\Geolocation\Region::current()` to get `'aunz'` or `'usa'` for the current request.

## How the region is decided

1. **Mapped domain (production):** the domain decides. Each domain's cached pages are always correct for its region.
2. **Any other host (staging, temporary Kinsta URLs):** the visitor's saved choice (the `rowdy_crowd_region` cookie) is used, otherwise AU. Page caching on these hosts can show the wrong region, so test with caching off or map the staging domains (see Hooks).

## First-visit redirect

Kinsta serves cached pages without running PHP, so the redirect happens in the browser:

1. A small inline script in `<head>` (`assets/redirect.js`) runs on mapped domains only.
2. **Returning visitor** (has the cookie): if the cookie's region doesn't match this domain, they're sent to their region's domain.
3. **First visit:** the page is hidden briefly (2.5s max) while the script calls `GET /wp-json/rowdy-crowd/v1/region`. That endpoint isn't cached. It reads Kinsta's `GEOIP_COUNTRY_CODE`:
   - US, Canada or Mexico → `usa`
   - anything else, or unknown → `aunz`

   The result is saved in the cookie. If it doesn't match the current domain, the visitor is redirected to the same path on the other domain.
4. **Search engine bots are never redirected.** Otherwise Googlebot, which crawls from the US, could never index the AU site.

If the lookup fails or times out, the page simply shows. Visitors with JavaScript off aren't redirected.

## Region switcher

`[region_switcher]` renders a dropdown with a flag and the region name. It normally goes in the header.

| Attribute | Default | Description |
| --- | --- | --- |
| `show_label` | `yes` | `no` shows only the flag on the toggle. |

Each option links to the current page on the other region's domain with `?rc_region=<region>`. The receiving domain saves the choice in its cookie and redirects to the clean URL. The links work without JavaScript; JS only opens and closes the dropdown.

Restyle it by overriding the CSS custom properties on `.rowdy-crowd-region-switcher` (`--rowdy-crowd-switcher-bg`, `-color`, `-hover`, `-radius`).

## Multi-domain URLs

While a mapped domain is being served, the module filters `home` and `siteurl` to that domain. Links, assets, REST URLs and canonical redirects then stay on the current domain. It also adds `hreflang` alternate links between the two domains, so search engines treat them as regional versions rather than duplicates.

## Hosting setup (Kinsta)

1. Add **both** domains to the same site in MyKinsta, with SSL.
2. Enable **Geolocation** (country level): MyKinsta → Sites → *site* → Tools → Geolocation.
3. Don't add MyKinsta geolocation redirect rules. They would ignore the visitor's switcher choice and fight this module.

For testing on staging, add `define( 'ROWDY_CROWD_TEST_COUNTRY', 'US' );` to `wp-config.php` to fake the visitor's country.

## Known limitations

- **Carts and logins are per domain.** Browser cookies can't be shared between `.com` and `.com.au`, so switching region starts a fresh cart and session. Logged-in staff need to log in on each domain.
- Content saved with absolute URLs (e.g. images) keeps whichever domain it was created on.
- WordPress sets the `wp-content` URL before plugins load, so theme and plugin assets keep loading from the primary domain on the other domain. Scripts, styles and images work fine across domains. Web fonts need an `Access-Control-Allow-Origin` header, which Kinsta support can add.

## Hooks

| Hook | Type | Description |
| --- | --- | --- |
| `rowdy_crowd_region` | filter | Region for the current request. |
| `rowdy_crowd_region_domains` | filter | Region → domain map. Add staging domains here, e.g. `array( 'aunz' => 'stg-au.example.com', 'usa' => 'stg-us.example.com' )`. |
| `rowdy_crowd_usa_countries` | filter | Countries routed to `usa`. Default `US`, `CA`, `MX`. |
| `rowdy_crowd_visitor_country` | filter | Detected visitor country code. |

## Files

| File | Purpose |
| --- | --- |
| `class-module.php` | Hooks: URL filtering, body class, `?rc_region=` handling, head CSS/script, hreflang. |
| `class-region.php` | Region logic: domain map, current region, cookie, country → region, cross-region URLs. |
| `class-rest-controller.php` | `GET /wp-json/rowdy-crowd/v1/region` endpoint. |
| `class-switcher.php` | `[region_switcher]` shortcode. |
| `assets/redirect.js` | First-visit redirect, printed inline in `<head>`. |
| `assets/switcher.js` / `switcher.css` | Dropdown behaviour and styles. |
| `assets/flags/` | `aunz.svg`, `usa.svg` flag images. |
