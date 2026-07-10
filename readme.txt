=== CHM RSS Display ===
Contributors: ciderhouse
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPL-2.0-or-later

Elementor widget that displays an external RSS feed (Wowbrary / CW Mars) as a carousel, grid, or list. Display only — never imports posts.

== Description ==

Adds an "RSS Book Feed" widget
to Elementor (Cider House category) with:

* Carousel / Grid / List views matching the approved design
* Full Elementor style controls (typography, colors, spacing, shadows)
* Transient caching with stale-on-error fallback
* Handles Wowbrary feed quirks (BOM, CDATA-embedded cover images,
  non-RFC-822 pubDate, "Author. Description" format)

== Changelog ==

= 1.0.2 =
* New: "Refresh RSS Feed" button in the front-end admin bar (admins only) —
  clears the cached feed and refetches immediately.
* New: cache keys are version-scoped, so every plugin update automatically
  refetches instead of serving data parsed by the old version.
* New: outbound links go directly to the destination — CW Mars catalog
  record for physical items, OverDrive for e-content — instead of through
  Wowbrary's redirector with its tracking parameters. Filterable via
  chm_rss_direct_links / chm_rss_catalog_record_base / chm_rss_econtent_base.
* Change: card links now send an origin-only referrer (dropped noreferrer),
  so the catalog can see the library site as the traffic source.

= 1.0.1 =
* Fix: cover images no longer collapse to their natural aspect when the theme
  ships an `img { height: auto }` reset (e.g. Hello Elementor) — the image now
  reliably fills the 2:3 frame.
* Fix: authors with initials ("H. M. Wolfe") no longer truncate to the first
  initial when split from the description.
* Fix: the feed's embedded format chip ("Downloadable Audio") no longer leaks
  into the description text.
* New: odd-shaped art (square CDs, landscape audio thumbs) renders full-size
  over a blurred echo of itself instead of being letterboxed or cropped.
* New: "Image Fit" style control (Fit / Fill) on the Image section.

= 1.0.0 =
* Initial release.
