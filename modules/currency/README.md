# Currency module

Lets visitors change currency with `[currency_switcher]`. The pricing itself is done by **WooPayments Multi-Currency**; this module adds the switcher and keeps URLs tidy. The region dropdown (see the [geolocation module](../geolocation/README.md)) also switches currency.

## Requirements

- WooPayments with **Multi-Currency** turned on, and at least two currencies enabled (WooPayments → Settings → Multi-currency).
- Currently enabled: AUD (store currency), EUR, NZD, GBP, USD.

If WooPayments Multi-Currency is off or has fewer than two currencies, `[currency_switcher]` outputs nothing.

## `[currency_switcher]`

A dropdown for the footer (or anywhere). The toggle shows the visitor's current currency code. The menu lists every currency enabled in WooPayments, in WooPayments' order, for example `Australian dollar ($ AUD)`. Adding or removing a currency in WooPayments updates the list with no code change.

The look comes from the shared dropdown (`assets/dropdown.css`): a transparent background with a white border and white text, no flags. Override the custom properties on `.rowdy-crowd-currency-switcher` to restyle it (`--rowdy-crowd-dropdown-color`, `-border`, `-hover`, `-menu-bg`, `-menu-color`, `-menu-hover`). The menu opens upwards if there's no room below, so it works at the bottom of the page.

| Attribute | Description |
| --- | --- |
| *(none)* | |

## How switching works

1. Each option links to the current page with `?currency=XXX`.
2. WooPayments handles that query var itself and saves the choice in the visitor's session (or user meta when logged in), then recalculates the cart.
3. This module then redirects to the same URL without `?currency=`, so the address bar stays clean and shared links don't carry a currency.

Links are `rel="nofollow"` so search engines don't crawl a page once per currency.

## Region and currency

Switching region in `[region_switcher]` also switches currency: Australia → AUD, United States → USD. The mapping lives in `Region::currencies()` in the geolocation module and can be changed with the `rowdy_crowd_region_currencies` filter. Changing currency in `[currency_switcher]` does **not** change region.

For visitors who haven't chosen anything, turn on WooPayments → Settings → Multi-currency → "Automatically switch customers' currency based on their location". Then a US visitor who lands on rowdycrowd.com straight away sees USD without clicking.

## Caching

Currency is per visitor, and pages are cached by Kinsta and WP Rocket. WooPayments has its own handling for this (a cache-optimised rendering mode, plus visitors with a WooCommerce session bypass the cache). The switcher's label is rendered on the server, so on a cached page it can show the wrong currency code. Test with caching on and switch currencies in a private window.

## Files

| File | Purpose |
| --- | --- |
| `class-module.php` | Registers the shortcode and the `?currency=` URL clean-up. |
| `class-currencies.php` | The only code that calls WooPayments (enabled and selected currencies). Safe when WooPayments is inactive. |
| `class-switcher.php` | `[currency_switcher]` shortcode. |

The dropdown markup, CSS and JS are shared with the region switcher (`includes/class-dropdown.php`, `assets/`).
