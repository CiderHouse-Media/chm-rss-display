# CHM RSS Display

A WordPress plugin by [Cider House Media](https://ciderhouse.media) that adds an **RSS Book Feed** widget to Elementor. It displays an external RSS feed (Wowbrary → Evergreen/CW Mars library catalogs) as a **carousel, grid, or list** — display only, it never imports feed items as posts.

## Features

- **Three views:** Carousel (Swiper, using Elementor's bundled library), Grid, and List — all responsive
- **Full Elementor styling:** native Group Controls for typography, colors, backgrounds, borders, shadows, spacing on every card element (card, image, category label, title, author, description, date, CTA, carousel navigation)
- **Optional hi-res covers:** enter your Evergreen ILS catalog's jacket endpoint in the widget to upgrade low-resolution feed thumbnails (160px) to licensed hi-res covers (~265×400) by ISBN, with automatic fallback to the original thumbnail (off by default)
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

## Hi-res covers

Feed thumbnails are often tiny (~160px) and look soft at card size. The widget's **Cover Image Quality** setting can upgrade them using your library catalog's cover service (Evergreen ILS):

- **Standard** — feed images only, no external calls (default)
- **Enhanced — find my library catalog automatically** — the plugin follows one feed link to your catalog and checks whether it serves covers. If it can't (some catalogs block automated requests), it silently falls back to Standard. Nothing breaks.
- **Enhanced — C/W MARS (Massachusetts)** — preset for C/W MARS member libraries
- **Enhanced — custom catalog URL** — enter your Evergreen jacket endpoint directly. It's usually your catalog address + `/opac/extras/ac/jacket/large/` — for example `https://catalog.example.org/opac/extras/ac/jacket/large/`. Your consortium's IT contact will know it. Test it in a browser by appending an ISBN; a cover image should load.

In Enhanced modes, your server sends only item ISBNs to the catalog, once per cache refresh. Results are remembered per ISBN, so lookups taper to zero after the first day. More presets can be added via the `chm_rss_jacket_presets` filter.

## Filters

| Filter | Default | Purpose |
|---|---|---|
| `chm_rss_cache_ttl` | widget setting / 6h | Cache lifetime in seconds |
| `chm_rss_user_agent` | `CHM-RSS-Display/{ver}; +site-url` | UA sent to the feed host |
| `chm_rss_resolve_hires` | `true` | Enable/disable hi-res cover lookup |
| `chm_rss_jacket_base` | widget setting | Override the resolved jacket endpoint globally |
| `chm_rss_jacket_presets` | `['cwmars' => …]` | Add consortium presets to the Cover Image Quality dropdown |
| `chm_rss_hires_time_budget` | `8.0` | Max seconds spent on cover lookups per cache refresh |

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
- Hi-res covers require an Evergreen jacket endpoint configured on the widget (e.g. a CW Mars "brick" host like `https://bark.cwmars.org/opac/extras/ac/jacket/large/`). If the consortium renames the host, update the widget setting.
- If the site runs a page cache (e.g. WP Rocket), new feed items appear after page-cache expiry/purge, not the moment the transient refreshes.

## Changelog

### 1.2.4
- Browser-side cover fallback chain: hi-res jacket → feed thumbnail (`data-fallback`) → styled placeholder; a down catalog host (e.g. bark.cwmars.org outage) can no longer produce broken images

### 1.2.3
- Description Length now actually governs List view (a CSS 2-line clamp was overriding it); carousel/grid keep a 3-line visual cap; mobile list shows 3 lines instead of hiding descriptions

### 1.2.2
- Fixed author-name truncation at initials ("J. D. Vance" → "J"); the author/description split now scans past initials and handles "By"-prefixed names

### 1.2.1
- Removed the background feed-refresh cron (server-load risk, little benefit for weekly feeds); freshness = Cache Duration setting

### 1.2.0
- Load More batching for Grid/List (deferred image loading), dots spacing/alignment controls, description length to 300 words, hardened image centering

### 1.1.0
- Cover Image Quality (auto-detect / C/W MARS preset / custom), Image Fit with blurred-backdrop default, per-ISBN lookup cache, 120-item cap
- Fixed Swiper asset registration race (carousel broken on frontend, fine in editor); no-Swiper horizontal-scroll fallback; proper cache-busting on updates

### 1.0.0
- Initial release: carousel/grid/list views, full Elementor style controls, transient caching with stale-on-error, Wowbrary feed-quirk handling, Evergreen hi-res cover resolution

## WordPress.org

The plugin ships with a directory-compliant `readme.txt` (including the required External Services disclosure), `uninstall.php` cleanup, `Requires Plugins: elementor` dependency header, and a `.distignore` for building the distribution zip.

## License

GPL-2.0-or-later. Copyright © Cider House Media.
