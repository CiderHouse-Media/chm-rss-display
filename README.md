# CHM RSS Display

A WordPress plugin by [Cider House Media](https://ciderhouse.media) that adds an **RSS Book Feed** widget to Elementor. It displays an external RSS feed (Wowbrary → Evergreen/CW Mars library catalogs) as a **carousel, grid, or list** — display only, it never imports feed items as posts.

## Features

- **Three views:** Carousel (Swiper, using Elementor's bundled library), Grid, and List — all responsive
- **Full Elementor styling:** native Group Controls for typography, colors, backgrounds, borders, shadows, spacing on every card element (card, image, category label, title, author, description, date, CTA, carousel navigation)
- **Smart cover images:** upgrades low-resolution feed thumbnails (160px) to licensed hi-res covers (~265×400) from the Evergreen catalog jacket service by ISBN, with automatic fallback to the original thumbnail
- **Resilient caching:** transient-based (1/6/12/24h, configurable per widget) with a stale-on-error fallback — a feed outage never breaks the page
- **Feed-quirk handling** for Wowbrary RSS:
  - UTF-8 BOM before the XML declaration
  - Cover images that exist only inside `content:encoded` CDATA HTML
  - Non-RFC-822 `pubDate` format (`7/7/2026 7:56:30 PM`)
  - `"Author Name. Description…"` combined description fields (author is split out, `"By "` prefixes stripped)
- **Accessible:** single-anchor cards, arrow buttons with labels, Swiper a11y module, `prefers-reduced-motion` respected (autoplay disabled, transitions removed), screen-reader "(opens in new tab)" hints
- **Performance-aware:** assets load only on pages using the widget, lazy-loaded images with fixed aspect-ratio boxes (no CLS), hi-res lookups HEAD-only under a time budget and only on cache refresh

## Requirements

- WordPress 6.0+
- PHP 7.4+
- Elementor 3.20+ (free)

## Installation

1. Download the latest release zip (or `Code → Download ZIP`)
2. WordPress Admin → Plugins → Add New → Upload Plugin
3. Activate — the **RSS Book Feed** widget appears in the Elementor panel under the **Cider House** category

## Usage

1. Drag **RSS Book Feed** onto a page
2. Paste the feed URL (e.g. `https://wowbrary.org/rss.aspx?l=XXXX&c=GEN`)
3. Pick a view (Carousel / Grid / List) and toggle card fields
4. Style everything in the Style tab

Cards link to the item's catalog page (CW Mars borrow link), opening in a new tab by default.

## Filters

| Filter | Default | Purpose |
|---|---|---|
| `chm_rss_cache_ttl` | widget setting / 6h | Cache lifetime in seconds |
| `chm_rss_user_agent` | `CHM-RSS-Display/{ver}; +site-url` | UA sent to the feed host |
| `chm_rss_resolve_hires` | `true` | Enable/disable hi-res cover lookup |
| `chm_rss_jacket_base` | `https://bark.cwmars.org/opac/extras/ac/jacket/large/` | Evergreen jacket endpoint (swap for another consortium) |
| `chm_rss_hires_time_budget` | `8.0` | Max seconds spent on cover lookups per cache refresh |
| `chm_rss_direct_links` | `true` | Link straight to the catalog/OverDrive instead of through Wowbrary's redirector |
| `chm_rss_catalog_record_base` | `https://belchertwn.cwmars.org/Record/` | Catalog record URL base for physical items |
| `chm_rss_econtent_base` | `https://cwmars.overdrive.com/media/` | OverDrive URL base for e-content items |

## Architecture

```
chm-rss-display/
├── chm-rss-display.php              # Header, Elementor guards, bootstrap
├── includes/
│   ├── class-plugin.php             # Singleton: hooks, widget/category/asset registration
│   ├── class-feed-fetcher.php       # Fetch + parse + cache + hi-res resolution (no Elementor deps)
│   └── class-feed-item.php          # Value object for a parsed item
├── widgets/
│   └── class-rss-feed-widget.php    # Elementor Widget_Base implementation
├── assets/
│   ├── css/chm-rss-widget.css       # Design-system defaults; controls override via {{WRAPPER}}
│   └── js/chm-rss-widget.js         # Swiper init via frontend/element_ready (editor-live)
└── readme.txt
```

`Feed_Fetcher` has zero Elementor dependencies and a public `parse()` — testable against fixture XML and reusable for a future shortcode or REST endpoint.

## Notes & known limitations

- Hi-res covers resolve for ISBNs present in the consortium's cover provider (typically physical books). Downloadable audio, e-books, and music items usually keep the feed's thumbnail.
- The default jacket host is an Evergreen "brick" hostname; if the consortium renames it, point `chm_rss_jacket_base` at the new host.
- If the site runs a page cache (e.g. WP Rocket), new feed items appear after page-cache expiry/purge, not the moment the transient refreshes.

## Changelog

### 1.0.2
- New: "Refresh RSS Feed" admin-bar button (front end, admins only) — clears the cached feed and refetches on the spot
- New: version-scoped cache keys — plugin updates automatically invalidate data parsed by the previous version
- New: outbound links go directly to the destination (CW Mars catalog record for physical items, OverDrive for e-content) instead of through Wowbrary's `l.aspx` redirector; filterable via `chm_rss_direct_links`, `chm_rss_catalog_record_base`, `chm_rss_econtent_base`
- Change: card links send an origin-only referrer (`referrerpolicy="strict-origin-when-cross-origin"`, `noreferrer` dropped) so the catalog sees the library site as the traffic source

### 1.0.1
- Fix: covers no longer collapse to natural aspect under theme `img { height: auto }` resets (e.g. Hello Elementor) — the image reliably fills its 2:3 frame
- Fix: authors with initials ("H. M. Wolfe") no longer truncate to the first initial when split from the description
- Fix: the feed's embedded format chip ("Downloadable Audio") no longer leaks into description text
- New: odd-shaped art (square CDs, landscape audio thumbnails) displays full-size over a blurred echo of the cover instead of letterboxing or cropping
- New: "Image Fit" style control (Fit — full cover / Fill — crop to frame)

### 1.0.0
- Initial release: carousel/grid/list views, full Elementor style controls, transient caching with stale-on-error, Wowbrary feed-quirk handling, Evergreen hi-res cover resolution

## License

GPL-2.0-or-later. Copyright © Cider House Media.
