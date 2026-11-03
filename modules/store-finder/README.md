# Store finder module

A store locator for Rowdy Crowd's stockists: a **Stockists** post type in wp-admin, a settings page for Google Maps, and the `[store_finder]` shortcode. It replaces the static list on the old Shopify find-a-store page with a searchable list next to a map.

## Requirements

| Requirement | Notes |
| --- | --- |
| Advanced Custom Fields (Pro is installed on the site) | The stockist fields are ACF fields, including the Google Map field. Without ACF the shortcode shows a message to administrators only. |
| Google Maps API key | **Maps JavaScript API** and **Places API** enabled (Places powers the address search on the stockist screen). Restrict the key by HTTP referrer to `rowdycrowd.com.au/*`, `rowdycrowd.com/*` and the staging domain. |
| Map ID (optional) | The map uses Google's advanced markers, which need a Map ID. Until one is saved, Google's `DEMO_MAP_ID` is used. Create a real one under Google Maps Platform → Map Management. |

## Setup

0. After the plugin is deployed, open any wp-admin page as an administrator. That saves the **Stockist details** field group to ACF (check ACF → Field Groups), which is what lets WP All Import see the fields.
1. **Stockists → Settings**: paste the API key (and Map ID, and change the pin colour if wanted) and save. This also gives ACF the key, so the address search works on the stockist screen.
   - Alternatively set `define( 'ROWDY_CROWD_GOOGLE_MAPS_API_KEY', '…' );` in `wp-config.php`. It takes priority and keeps the key out of the database.
2. **Stockists → Add new**: add the stores (below).
3. Put `[store_finder]` on a page.

## Adding a stockist

The post **title** is the store name. The fields:

| Field | Required | Notes |
| --- | --- | --- |
| Store location | Yes | Google Map field. Search for the store and pick its address. The pin, suburb, state and postcode all come from this, so the address is only entered once. |
| Street address | No | Replaces the street part of the address shown to customers, e.g. `Shop 2, 76 George Street`. Blank uses the street from the map. |
| Phone | No | Shown as a tap-to-call link. |
| Website | No | Include `https://`. Shown without the scheme, e.g. `seasontotaste.au`. |
| Region | Yes | Australia or United States. Defaults to Australia. |

A stockist **without a map location is not shown on the site**. The Stockists list flags these in the Address column.

## `[store_finder]`

```
[store_finder]
[store_finder region="all" height="70vh" class="my-class"]
```

| Attribute | Default | Notes |
| --- | --- | --- |
| `region` | `current` | `current` shows the stores for the site being viewed (rowdycrowd.com.au → Australia, rowdycrowd.com → United States). `aunz`, `usa` or `all` override that. |
| `height` | `560px` | Height of the map and list. A CSS length (`px`, `vh`, `rem`, `em`, `%`). |
| `class` | empty | Extra CSS classes on the wrapper. |

What visitors get:

- **Search** (matches store name, street, suburb, state or postcode; every word must match).
- **State chips**: one per state that has stores, in the state's brand colour, with a store count (e.g. `NSW 31`), plus **All**. Pick one to filter. With more than 12 states (e.g. the US) a dropdown is used instead.
- **Stores near me**: asks the browser for the visitor's location, then sorts the list nearest first with the distance on each card (km, or miles on the US site), marks the visitor on the map and frames the nearest few stores. Press it again to go back. If location is blocked, times out or isn't available, a message says so and suggests searching instead. The location stays in the browser: nothing is sent to the server or stored.
- **A list** sorted by state, suburb and name, with address, phone, website and **Directions** (opens Google Maps).
- **A map** with a pin per store. **Clicking anywhere on a store's card** zooms the map to that store and opens its info window; clicking a pin does the reverse and highlights the card. Links inside a card (phone, website, directions) still work as links. The store name is a real button underneath, so keyboard and screen-reader users can select a store too.
- Search and the filters update the list and the pins together.
- Stores at exactly the same address are spread into a small circle so each pin can be clicked.
- On phones the map sits above the list and the chips scroll sideways in one row.

**When nothing matches**, a "No stores match your search" card takes the list's place (the map doesn't move) with a **Clear search and filters** button, and the map goes back to the whole country.

If there are no stores for the region at all, visitors see "We don't have any stores listed here yet."

## How it works

1. `Stockists::for_region()` queries published `rc_stockist` posts (one meta query for region, one cache prime for all fields) and builds a `Stockist` for each one that has a location.
2. The shortcode renders the **whole list on the server** (`templates/store-finder.php`). Each item carries `data-lat`, `data-lng`, `data-state` and `data-search`. The list therefore works without JavaScript, is indexable, and is cached with the page.
3. `store-finder.js` adds the search, the state filter and the map. It reads everything from the list items, and builds info windows by copying the item's content, so the list and map can't disagree.
4. Google calls `rowdyCrowdStoreFinderInit` once the Maps API has loaded.

The region comes from the domain, so cached pages stay correct per site (see the [geolocation module](../geolocation/README.md)).

### Data model

| Thing | Stored as |
| --- | --- |
| Stores | `rc_stockist` posts (not public: no pages of their own) |
| Fields | An ACF field group, **"Stockist details"** (`group_rowdy_crowd_stockist`), saved in ACF's database and visible under ACF → Field Groups. Meta keys `rowdy_crowd_location`, `rowdy_crowd_address`, `rowdy_crowd_phone`, `rowdy_crowd_website`, `rowdy_crowd_region`. See "How the fields are saved". |
| Field group version | `rowdy_crowd_stockist_fields_version` option |
| Settings | `rowdy_crowd_store_finder` option (`api_key`, `map_id`, `pin_color`) |

### How the fields are saved

The fields are defined in code (`class-stockist-fields.php`) **and saved to ACF's database** as a normal field group. Tools that read ACF's database, such as the **WP All Import ACF add-on**, can't see a group that exists only in PHP, so it has to be in the database.

- The first time an **administrator** opens any wp-admin page after the plugin is deployed, the group is saved. Until someone does, WP All Import won't list the fields.
- If the group is ever deleted, the next administrator page load saves it again.
- The group is updated in place (never duplicated) when `Stockist_Fields::VERSION` is bumped. A developer bumps it whenever the definition changes.
- **Don't edit "Stockist details" on the ACF screen.** Edits are overwritten the next time the definition changes. Ask for the change to be made in the code instead.

`rowdy_crowd_location` is ACF's serialised Google Map value (`address`, `lat`, `lng`, `street_number`, `street_name`, `city`, `state`, `state_short`, `post_code`, …). Anything that imports stockists must write that format.

### Front end

The CSS, the JS and the Google Maps script load only on pages that use the shortcode. Without an API key there is no Google script and no map, and cards can't be selected: visitors get the list as a grid of cards (search, chips and **Stores near me** still work), and administrators see a note saying why.

If Google rejects the key, the map is removed and the list falls back to the same card grid.

## Styling

The finder uses Rowdy Crowd's brand palette, defined as custom properties on `.rowdy-crowd-store-finder`:

| Property | Colour | Used for |
| --- | --- | --- |
| `--rowdy-crowd-ink` | `#403A35` | Body text. |
| `--rowdy-crowd-blue` | `#316195` | Links, focus rings, the pressed "Stores near me" button, the NSW chip. |
| `--rowdy-crowd-teal` | `#00BBB4` | "Stores near me" button, the chosen card, the VIC chip. |
| `--rowdy-crowd-pink` | `#E93CAC` | State chips (ACT, WA). The map pins use the **pin colour** setting, which defaults to this. |
| `--rowdy-crowd-orange` | `#FF6418` | QLD chip, location error messages. |
| `--rowdy-crowd-lime` | `#B7BF10` | SA chip, distance badges, the "no stores" card. |
| `--rowdy-crowd-on-colour` | `#1F1B19` | Text on the pink, teal, orange and lime fills. |

**Contrast:** only blue and the ink are used as text colours on white. Pink, teal, orange and lime are too light for that (under 4.5:1), so they're used as fills and tints with near-black text, which passes. Keep that if you change the colours. State chip colours come from `Stockists::chip_colour()`. Other custom property: `--rowdy-crowd-store-finder-height` (set by the `height` attribute).

## Hooks

None yet. Region helpers come from the geolocation module (`Region::current()`).

## Files

| File | Purpose |
| --- | --- |
| `class-module.php` | Registers the parts below. |
| `class-stockist-post-type.php` | The Stockists post type, store-name placeholder and admin list columns. |
| `class-stockist-fields.php` | The ACF field group definition, and saving it to ACF's database. |
| `class-stockist.php` | One store: reads the fields, formats address, phone, website, directions link and search text. |
| `class-stockists.php` | Queries stores by region; lists the states, their store counts and chip colours. |
| `class-settings.php` | Stockists → Settings (API key, Map ID, pin colour) and the key constant. |
| `class-shortcode.php` | `[store_finder]`: attributes, asset loading, template. |
| `templates/store-finder.php` | Markup for the finder. |
| `assets/store-finder.css` / `.js` | Layout, and the search, filter and map behaviour. |

## Gotchas

- **The map needs a pin.** Stores without a Google Map location are left out. They show a warning in the Stockists list.
- **Stores only show on their own region's site** unless the shortcode uses `region="all"`. Stores with no region saved count as Australia.
- **The key needs the Places API as well as Maps JavaScript**, or the address search on the stockist screen won't work even though the front-end map does.
- **The key must allow every domain** the site is served from, including the staging domain, or the map is replaced by the list (and the stockist screen's address search fails).
- **`DEMO_MAP_ID`** is Google's sample ID. It works, but create a real Map ID for the live site.
- **The info window's header row is switched off** (`headerDisabled`, a documented Google option). Google reserves a 48px row at the top for its close button, which left a gap above the text, so the popup draws its own close button. The popup's padding is tuned to Google's own 12px (top, left and bottom), measured on the live site.
- **Buttons:** the Elementor kit restyles every `<button>` on hover and focus, so each of this module's buttons (`near-me`, `chip`, `select`, `clear`, `info-close`) repeats its look for every state in `store-finder.css`. Keep that if you add or change a button.
- **"Stores near me" needs HTTPS** (browsers only share location on secure pages) and the visitor's permission. It measures straight-line distance, not driving distance.
- **Bulk import isn't built.** The Google Map value is a serialised array, so an import has to write that structure (address plus latitude and longitude at least).

## Testing

During development the list, search, state chips and dropdown, "Stores near me" (success, permission denied, timeout and no-geolocation, with the browser's location faked), card and pin selection, markers, fan-out, the nothing-matches layout, the no-key and key-rejected fallbacks and the phone layout were checked in a browser against a stand-in for the Maps API, with stubbed WordPress and ACF. A simulated copy of the Elementor kit's button hover rule was included, and every button kept its look. The info window's spacing was measured against the real Google Maps on the staging site.

Not covered: the real map's pins and clicks (the stand-in replaces Google), and a real browser location prompt. After deploying, check on staging that pins land in the right places, that clicking a card zooms to the store, that **Stores near me** asks for permission and sorts by distance, that the address search works on the stockist screen, and that both domains load the map.
