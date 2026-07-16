# CLAUDE.md — CHM RSS Display

Project context for Claude Code. Read this before making changes.

## What this is

WordPress plugin by Cider House Media (`ciderhouse.media`). Registers one Elementor widget, **RSS Book Feed** (`chm_rss_feed`), that displays an external RSS feed as a carousel, grid, or list. Display-only — never imports posts. Built July 2026; originally for a library client's Wowbrary "new arrivals" feed, then rebranded client-agnostic as CHM (Cider House Media). Do not reintroduce client names into code, slugs, or docs.

## Architecture (intentional decisions — don't undo casually)

- `includes/class-feed-fetcher.php` has **zero Elementor dependencies**. Fetch/parse/cache/hi-res logic stays here; `parse()` is public for testing against fixture XML.
- `includes/class-feed-item.php` — value object; all sanitization happens at parse time.
- `widgets/class-rss-feed-widget.php` — controls + render only. Style controls use native Elementor Group Controls with `{{WRAPPER}}` selectors; CSS defaults in `assets/css/chm-rss-widget.css` mirror the approved design comp, controls override. Cover images render as object-fit:contain over a blurred same-URL backdrop layer (`__media-bg`) by default — non-2:3 art (games, CDs) sits centered in a soft blur letterbox; an Image Fit style control (prefix class `chm-rss-imgfit-{blur|cover|contain}`) switches to hard crop or plain letterbox. Do not remove the backdrop span without checking that control.
- Carousel uses **Elementor's bundled Swiper** (no shipped copy). Assets register on `elementor/frontend/after_register_scripts|styles` (NOT bare wp_enqueue_scripts — that races Elementor and drops the swiper dep; bit us in 1.0.0). Widget declares 'swiper'/'e-swiper' in get_style_depends and 'swiper' in get_script_depends; our script must never hard-depend on the swiper handle. CSS includes a :not(.swiper-initialized) horizontal-scroll fallback so an uninitialized carousel is never blank. ALWAYS bump CHM_RSS_VERSION on any asset change — same-version updates serve stale cached CSS/JS. JS registers via `frontend/element_ready/chm_rss_feed.default` so it re-inits live in the editor. Container class is version-aware (`swiper` vs `swiper-container` via the `e_swiper_latest` experiment check).
- `is_dynamic_content(): true` — required so Elementor's element cache (3.22+) never freezes remote data.

## Feed quirks (Wowbrary — verified against live feed)

`https://wowbrary.org/rss.aspx?l={library}&c={category}` — RSS 2.0, ~20 items, weekly.

1. **UTF-8 BOM** before the XML declaration — stripped before `DOMDocument::loadXML`.
2. **Cover image exists ONLY in `content:encoded` CDATA HTML** (`img src` on `wowbrary.blob.core.windows.net`, ~160×127px). No enclosure/media:content. Parsed via a second DOMDocument pass.
3. **`pubDate` is NOT RFC-822**: `7/7/2026 7:56:30 PM`. Parsed with `DateTime::createFromFormat('n/j/Y g:i:s A', …, America/New_York)`, `strtotime` fallback, null fallback.
4. **`description` = "Author Name. Description…"** — a leading `"By "` is stripped first, then split on the `". "` that ends the name: when the cut lands on an initial ("J. D. Vance" cuts at "J", "S.M. Beiko" at "S.M") scanning continues to the next `". "` (1.2.2 fix — do not revert to first-`". "`). Prefixes > 60 chars or > 6 words are treated as prose; if scanning overruns the caps, the last name-shaped candidate ending in an initial ("Malcolm X.") is used.
5. Item `link` = Wowbrary redirect → CW Mars catalog borrow page; ISBN is in the link's `i=` query param (17/20 items have one).
6. Feed host **blocks generic crawlers** — fetches send a descriptive UA (`CHM-RSS-Display/{ver}; +{home_url}`), filterable via `chm_rss_user_agent`.

## Hi-res cover cascade (opt-in via Cover Image Quality dropdown)

Feed thumbnails blur at card size. The widget's **Cover Image Quality** select maps to a jacket base: 'feed' → '' (off, default), 'auto' → discovery (follow up to 3 item-link redirects to the catalog host, skip OverDrive hosts, probe {host}/opac/extras/ac/jacket/large/{isbn}; result incl. failure cached per feed in `chm_rss_stale_discovery_{md5(feed)}`, failures retried weekly — NOTE: fails for C/W MARS skin hosts, they're Cloudflare-blocked, hence the preset), 'cwmars' → bark preset (extend via `chm_rss_jacket_presets` filter), 'custom' → user URL. When a base resolves, the fetcher HEAD-requests `{base}{isbn}` per item on cache refresh: **200 = real cover (~265×400), 404 = 1×1 blank → keep thumbnail**. Time-budgeted (`chm_rss_hires_time_budget`, 8s default), 3s per HEAD. Results persist in a per-ISBN option cache (`chm_rss_stale_jackets_{md5(base)}`, capped ~400 entries): hits are permanent, misses re-checked weekly — so coverage accumulates across refreshes even on 100+ item feeds (verified: 28→40→50 hi-res over three simulated refreshes of the 102-item CUS1001 feed). Items-to-show slider max is 120. Empty setting = feature fully off (WP.org external-services compliance — never contact a host the owner didn't opt into). Known-good endpoint for CW Mars libraries: `https://bark.cwmars.org/opac/extras/ac/jacket/large/` (it's a "brick" hostname; may change — physical-book ISBNs resolve, e-audio/e-book/music usually don't).

## Caching (transients)

DESIGN RULE (learned in 1.2.0→1.2.1): no background cron, no feed registry, no change-detection. A cron system shipped in 1.2.0 re-fetched all feeds hourly and overloaded the slow dev server (editor became unloadable); it was removed in 1.2.1 and the main file clears any leftover `chm_rss_refresh` schedule on init. Freshness is TTL-only — the feeds update weekly, so the Cache Duration setting (1–24h) already bounds staleness. Do not reintroduce background refresh without explicit sign-off and a real server-cron environment. Cache keys include CHM_RSS_VERSION so plugin updates always start fresh.

Transient `chm_rss_{md5(url|jacket_base)}`, TTL from widget (1/6/12/24h, default 6h) / `chm_rss_cache_ttl` filter; 60s TTL inside the Elementor editor. Stale copy kept in non-autoloaded option `chm_rss_stale_{hash}`; served on fetch failure. `uninstall.php` deletes all of it. Page caches (WP Rocket on client sites) sit on top — new items appear after page-cache purge.

## WP.org submission state (as of 2026-07-14)

- readme.txt: compliant (short desc 134 chars, Tested up to 7.0, External Services section, FAQ, changelog)
- Header: `Requires at least: 6.5`, `Requires Plugins: elementor`, License URI, Author URI
- `uninstall.php` cleanup, `.distignore` for the dist zip
- **TODO before submitting:** replace `Contributors: ciderhousemedia` in readme.txt with the real wordpress.org username; add screenshots + banner/icon to SVN `assets/` after approval
- Build dist zip: `rsync -a --exclude-from=.distignore . /tmp/chm-rss-display/ && cd /tmp && zip -r chm-rss-display.zip chm-rss-display`
- Submit at https://wordpress.org/plugins/developers/add/ — slug request `chm-rss-display`

## Testing

No formal test suite yet (candidate for PHPUnit + fixture XML — `Feed_Fetcher::parse()` was designed for it). Manual verification so far: parser validated against the live feed (20/20 items: images, authors, dates), all PHP files `php -l` clean, JS `node --check` clean. Not yet verified on a live WP install: Swiper init, editor live re-init, style control overrides.

## Conventions

- Prefix everything: `chm_rss_` (functions/filters/transients), `ChmRss\` (namespace), `chm-rss-` (slugs/handles/CSS), `--chm-` (CSS vars)
- WordPress coding standards, tabs, escaped output everywhere (`esc_html`, `esc_url`, `esc_attr`), i18n on every string with text domain `chm-rss-display`
- Bump version in: plugin header, `CHM_RSS_VERSION`, readme.txt `Stable tag`, README.md + readme.txt changelogs
