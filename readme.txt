=== CHM RSS Display ===
Contributors: ciderhousemedia
Tags: elementor, rss, feed, carousel, books
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.2.3
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display an external RSS feed as a customizable carousel, grid, or list in Elementor. Optimized for library feeds. Never imports posts.

== Description ==

CHM RSS Display adds an **RSS Book Feed** widget to Elementor that renders an external RSS feed as a responsive **carousel, grid, or list** of cards — cover image, title, author, description, date, and a call-to-action link. It is display-only: feed items are never imported as posts.

Originally built for public library "new arrivals" feeds (Wowbrary), it handles several real-world feed quirks automatically:

* UTF-8 BOM before the XML declaration
* Cover images embedded only inside `content:encoded` CDATA HTML
* Non-standard `pubDate` formats
* Combined "Author Name. Description" fields (author is split out automatically)

**Styling** uses Elementor's native controls throughout — typography, colors, backgrounds, borders, shadows, and spacing for every card element, plus carousel arrows/dots styling, all responsive.

**Caching** is transient-based (1–24 hours, configurable per widget) with a stale-on-error fallback, so a feed outage never breaks your page.

**Optional hi-res covers:** the Cover Image Quality setting can upgrade low-resolution feed thumbnails to your library catalog's licensed cover images by ISBN (Evergreen ILS systems). Choose "find my library catalog automatically" and the plugin discovers your catalog from the feed's own links — if the catalog can't provide covers, feed images are used and nothing breaks. A C/W MARS preset and a custom URL option are also available. Off by default.

= External services =

This plugin connects to external services only as configured by you:

1. **The RSS feed URL you enter** (e.g. a Wowbrary feed). Your server requests this URL on cache refresh to retrieve the feed content. The request includes your site URL in the user-agent string so the feed operator can identify the traffic source. No visitor data is sent. See the feed operator's own terms; for Wowbrary see https://wowbrary.org/.
2. **Your library catalog, if you choose an Enhanced cover option** (off by default). In automatic mode, your server follows one feed link to identify the catalog the feed already points to; in all Enhanced modes it then sends requests containing only item ISBNs to check for higher-resolution cover images. No visitor data is sent. This endpoint is operated by your own library or consortium.
3. **Cover images** referenced by the feed (or the jacket service) are loaded by visitors' browsers directly from those hosts, as with any externally hosted image.

No data is sent to Cider House Media or any analytics service.

== Installation ==

1. Install and activate Elementor (free) if you haven't already.
2. Upload the plugin zip via Plugins → Add New → Upload Plugin, then activate.
3. Edit a page with Elementor and drag the **RSS Book Feed** widget (Cider House category) onto the page.
4. Paste your feed URL, choose Carousel, Grid, or List, and style it in the Style tab.

== Frequently Asked Questions ==

= Does this import feed items as posts? =

No, never. The widget renders the feed at display time from a cached copy. Nothing is added to your content tables.

= Which feeds are supported? =

Any RSS 2.0 feed with title, link, and description works. The widget is optimized for Wowbrary library feeds and understands their non-standard fields, but it is not limited to them.

= Why are some cover images low resolution? =

By default the widget uses the image URLs your feed provides, which are often small. Set Cover Image Quality to "Enhanced — find my library catalog automatically" in the widget's Feed section: if your catalog runs Evergreen ILS and serves covers, they'll be upgraded automatically. Some items (e-books, audio, music) may not have catalog covers and keep the feed image.

= How fresh is the displayed feed? =

You choose the cache window per widget (1, 6, 12, or 24 hours). If your site also runs a page cache, new items appear after that cache clears too.

= Does it work without Elementor? =

No. Elementor (free, 3.20+) is required; the plugin registers an Elementor widget.

== Screenshots ==

1. Carousel view with styled cards
2. Grid view
3. List view
4. Widget controls in the Elementor panel

== Changelog ==

= 1.2.3 =
* Fixed: the Description Length setting had no visible effect in List view — a stylesheet rule clamped descriptions to 2 lines regardless. List view now shows the full trimmed text; Carousel/Grid cards keep a 3-line cap for even card heights
* Improved: List view descriptions on small screens show 3 lines instead of being hidden

= 1.2.2 =
* Fixed: authors with initials ("J. D. Vance", "S.M. Beiko") were truncated at the first initial; the split between author and description now scans past initials to the end of the name
* Fixed: authors prefixed with "By" in the feed could be dropped entirely when the full name ran past the length guard

= 1.2.1 =
* Removed: the 1.2.0 background feed-refresh system — it could overload smaller servers and added complexity without real benefit for weekly feeds. Freshness is governed by the Cache Duration setting; plugin updates always start from a fresh cache.

= 1.2.0 =
* New: Load More button for Grid and List views — items load in batches; hidden items defer their images
* New: carousel dots controls — spacing between dots, space above, and alignment
* Improved: Description Length now goes up to 300 words (full descriptions in List view)
* Fixed: cover images could pin to the top of the frame when theme/Elementor image rules overrode sizing

= 1.1.0 =
* New: Cover Image Quality setting — automatic library catalog detection, C/W MARS preset, or custom URL (replaces the raw endpoint field)
* New: Image Fit setting — covers that don't match the aspect ratio display centered over a blurred backdrop (default), cropped, or letterboxed
* New: per-ISBN cover-lookup cache — hi-res coverage builds across refreshes and lookups taper to zero
* Improved: Items to Show now supports up to 120 items for large feeds
* Fixed: Swiper assets could fail to load on the frontend due to a registration race, breaking the carousel outside the editor
* Fixed: carousel now degrades to a horizontal scroller if Swiper is unavailable — it can no longer render blank
* Fixed: asset version now busts browser/page caches on plugin updates

= 1.0.0 =
* Initial release: carousel/grid/list views, full Elementor style controls, transient caching with stale-on-error fallback, library feed quirk handling, optional Evergreen hi-res cover resolution.

== Upgrade Notice ==

= 1.1.0 =
Fixes frontend carousel loading; adds cover quality auto-detection and image fit options. After updating, purge any page cache.

= 1.0.0 =
Initial release.
