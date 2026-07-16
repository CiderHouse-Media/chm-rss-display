<?php
namespace ChmRss;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetches, parses and caches an external RSS feed.
 *
 * Deliberately has zero Elementor dependencies so it can be reused
 * (shortcode, REST, WP-CLI) and unit-tested against raw XML.
 *
 * Wowbrary specifics handled here:
 * - UTF-8 BOM before the XML declaration.
 * - Cover image exists ONLY inside <content:encoded> CDATA HTML.
 * - pubDate is NOT RFC-822 ("7/7/2026 7:56:30 PM").
 * - description is "Author Name. Description…".
 */
class Feed_Fetcher {

	const TRANSIENT_PREFIX = 'chm_rss_';
	const STALE_PREFIX     = 'chm_rss_stale_';
	const DEFAULT_TTL      = 6 * HOUR_IN_SECONDS;
	const EDITOR_TTL       = 60;

	/**
	 * Get parsed feed items, served from cache when possible.
	 *
	 * @param string $url         Feed URL (https).
	 * @param int    $ttl         Cache TTL in seconds. 0/negative falls back to default.
	 * @param string $jacket_base Optional Evergreen jacket endpoint for hi-res covers ('' = disabled).
	 * @return Feed_Item[] Possibly empty array. Never WP_Error — errors degrade to stale or [].
	 */
	public function get_items( $url, $ttl = 0, $jacket_base = '' ) {
		$url = esc_url_raw( trim( (string) $url ), [ 'https', 'http' ] );
		if ( '' === $url ) {
			return [];
		}

		if ( 'auto' !== $jacket_base ) {
			$jacket_base = esc_url_raw( trim( (string) $jacket_base ), [ 'https' ] );
		}

		$ttl = (int) apply_filters( 'chm_rss_cache_ttl', $ttl > 0 ? $ttl : self::DEFAULT_TTL, $url );

		// In the Elementor editor, keep the cache very short so editors see fresh data.
		if ( $this->is_elementor_editor() ) {
			$ttl = min( $ttl, self::EDITOR_TTL );
		}

		// Version in the key: plugin updates always start from a fresh cache.
		$key    = md5( $url . '|' . $jacket_base . '|' . CHM_RSS_VERSION );
		$cached = get_transient( self::TRANSIENT_PREFIX . $key );

		if ( is_array( $cached ) ) {
			return $this->hydrate( $cached );
		}

		$xml = $this->fetch( $url );

		if ( is_wp_error( $xml ) ) {
			// Stale-on-error: serve the last good copy rather than an empty section.
			$stale = get_option( self::STALE_PREFIX . $key );
			return is_array( $stale ) ? $this->hydrate( $stale ) : [];
		}

		return $this->build_and_cache( $key, $xml, $url, $jacket_base, $ttl );
	}

	/**
	 * Parse XML, resolve covers, and store transient + stale copy + hash.
	 * Shared by page-load refreshes and the hourly change-detection cron.
	 *
	 * @param string $key         Cache key.
	 * @param string $xml         Raw feed XML.
	 * @param string $url         Feed URL.
	 * @param string $jacket_base Jacket base ('' | 'auto' | URL).
	 * @param int    $ttl         Cache TTL.
	 * @return Feed_Item[]
	 */
	protected function build_and_cache( $key, $xml, $url, $jacket_base, $ttl ) {
		$items = $this->parse( $xml );

		if ( 'auto' === $jacket_base ) {
			$jacket_base = $this->discover_jacket_base( $items, $url );
		}

		$items = $this->resolve_hires_images( $items, $jacket_base );
		$raw   = array_map( 'get_object_vars', $items );

		set_transient( self::TRANSIENT_PREFIX . $key, $raw, $ttl > 0 ? $ttl : self::DEFAULT_TTL );

		if ( ! empty( $raw ) ) {
			update_option( self::STALE_PREFIX . $key, $raw, false ); // autoload: no.
		}

		return $items;
	}

	/**
	 * Fetch raw feed XML.
	 *
	 * @param string $url Feed URL.
	 * @return string|\WP_Error Raw XML body.
	 */
	protected function fetch( $url ) {
		$response = wp_safe_remote_get(
			$url,
			[
				'timeout'    => 10,
				'user-agent' => apply_filters(
					'chm_rss_user_agent',
					'CHM-RSS-Display/' . CHM_RSS_VERSION . '; +' . home_url( '/' )
				),
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( 200 !== (int) $code || '' === $body ) {
			return new \WP_Error( 'chm_rss_http', 'Feed returned HTTP ' . $code );
		}

		return $body;
	}

	/**
	 * Parse raw RSS XML into Feed_Item objects.
	 *
	 * Public so it can be exercised directly in tests with fixture XML.
	 *
	 * @param string $xml Raw XML.
	 * @return Feed_Item[]
	 */
	public function parse( $xml ) {
		// Strip UTF-8 BOM — the Wowbrary feed ships one and DOMDocument chokes on it.
		$xml = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $xml );

		if ( '' === trim( $xml ) ) {
			return [];
		}

		$previous = libxml_use_internal_errors( true );
		$doc      = new \DOMDocument();
		$loaded   = $doc->loadXML( $xml, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded ) {
			return [];
		}

		$items = [];

		foreach ( $doc->getElementsByTagName( 'item' ) as $node ) {
			$title = $this->node_text( $node, 'title' );
			$link  = esc_url_raw( $this->node_text( $node, 'link' ), [ 'https', 'http' ] );

			if ( '' === $title || '' === $link ) {
				continue;
			}

			list( $author, $description ) = $this->split_author( $this->node_text( $node, 'description' ) );

			$items[] = new Feed_Item(
				[
					'title'       => sanitize_text_field( $title ),
					'author'      => sanitize_text_field( $author ),
					'description' => sanitize_text_field( $description ),
					'link'        => $link,
					'image'       => $this->extract_image( $node ),
					'timestamp'   => $this->parse_date( $this->node_text( $node, 'pubDate' ) ),
					'category'    => sanitize_text_field( $this->node_text( $node, 'category' ) ),
					'isbn'        => $this->extract_isbn( $link ),
				]
			);
		}

		return $items;
	}

	/**
	 * Text content of the first matching child tag.
	 *
	 * @param \DOMElement $node Item node.
	 * @param string      $tag  Child tag name.
	 * @return string
	 */
	protected function node_text( $node, $tag ) {
		$list = $node->getElementsByTagName( $tag );
		return $list->length ? trim( (string) $list->item( 0 )->textContent ) : '';
	}

	/**
	 * Extract the first <img src> from the item's content:encoded HTML.
	 *
	 * The image is NOT in an enclosure or media:content element — only
	 * inside CDATA HTML — so we parse that fragment separately.
	 *
	 * @param \DOMElement $node Item node.
	 * @return string Sanitized https image URL, or ''.
	 */
	protected function extract_image( $node ) {
		$encoded = $node->getElementsByTagNameNS( 'https://purl.org/rss/1.0/modules/content/', 'encoded' );
		if ( ! $encoded->length ) {
			// Some feeds use the canonical http namespace.
			$encoded = $node->getElementsByTagNameNS( 'http://purl.org/rss/1.0/modules/content/', 'encoded' );
		}
		if ( ! $encoded->length ) {
			return '';
		}

		$html = (string) $encoded->item( 0 )->textContent;
		if ( '' === trim( $html ) ) {
			return '';
		}

		$previous = libxml_use_internal_errors( true );
		$fragment = new \DOMDocument();
		$fragment->loadHTML(
			'<?xml encoding="utf-8"?><div>' . $html . '</div>',
			LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET
		);
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		$imgs = $fragment->getElementsByTagName( 'img' );
		if ( ! $imgs->length ) {
			return '';
		}

		$src = esc_url_raw( trim( $imgs->item( 0 )->getAttribute( 'src' ) ), [ 'https' ] );

		return $src;
	}

	/**
	 * Split "Author Name. Description…" into [ author, description ].
	 *
	 * Only treats the prefix as an author when it is plausibly a name
	 * (short, no sentence-length run-ons).
	 *
	 * @param string $description Raw description text.
	 * @return array{0:string,1:string}
	 */
	protected function split_author( $description ) {
		$description = trim( (string) $description );

		// The feed sometimes prefixes items with "By Author Name". Strip it
		// before scanning so it doesn't count against the name-length caps,
		// but keep $description intact for the no-author return.
		$scan = preg_replace( '/^by\s+/i', '', $description );

		$fallback = null; // Last name-shaped candidate, if scanning overruns.
		$offset   = 0;

		while ( true ) {
			$pos = strpos( $scan, '. ', $offset );
			if ( false === $pos ) {
				break;
			}

			$candidate = substr( $scan, 0, $pos );
			$rest      = trim( substr( $scan, $pos + 2 ) );

			// A real author prefix is short; long prefixes are just the first sentence.
			if ( '' === $candidate || strlen( $candidate ) > 60 || str_word_count( $candidate ) > 6 ) {
				break;
			}

			// When the cut lands on an initial ("J. D. Vance. …" cuts at "J",
			// "S.M. Beiko. …" cuts at "S.M"), the ". " belongs to the name —
			// keep scanning for the period that actually ends the prefix.
			$space     = strrpos( $candidate, ' ' );
			$last_word = false === $space ? $candidate : substr( $candidate, $space + 1 );
			if ( preg_match( '/^[A-Z](\.[A-Z])*$/', $last_word ) ) {
				if ( str_word_count( $candidate ) >= 2 ) {
					$fallback = [ $candidate, $rest ];
				}
				$offset = $pos + 2;
				continue;
			}

			return [ $candidate, $rest ];
		}

		// Scanning ran past the caps or out of text while extending initials —
		// a name ending in an initial ("Malcolm X. Long prose…") is still valid.
		if ( null !== $fallback ) {
			return $fallback;
		}

		return [ '', $description ];
	}

	/**
	 * Parse the feed's non-standard pubDate ("7/7/2026 7:56:30 PM").
	 *
	 * @param string $raw Raw pubDate string.
	 * @return int|null Unix timestamp or null.
	 */
	protected function parse_date( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return null;
		}

		$dt = \DateTime::createFromFormat( 'n/j/Y g:i:s A', $raw, new \DateTimeZone( 'America/New_York' ) );
		if ( $dt instanceof \DateTime ) {
			return $dt->getTimestamp();
		}

		// RFC-822 or anything else strtotime can manage.
		$ts = strtotime( $raw );

		return false !== $ts ? $ts : null;
	}

	/**
	 * Extract the ISBN/EAN from a Wowbrary item link's i= query parameter.
	 *
	 * @param string $link Item link.
	 * @return string 10–13 digit identifier, or ''.
	 */
	protected function extract_isbn( $link ) {
		$query = wp_parse_url( $link, PHP_URL_QUERY );
		if ( ! $query ) {
			return '';
		}
		parse_str( $query, $params );
		$isbn = isset( $params['i'] ) ? preg_replace( '/[^0-9Xx]/', '', $params['i'] ) : '';

		return ( strlen( $isbn ) >= 10 && strlen( $isbn ) <= 13 ) ? $isbn : '';
	}

	/**
	 * Upgrade item images to licensed cover jackets from an Evergreen ILS
	 * catalog, where configured. Feed thumbnails are often tiny (~160px)
	 * and blur at card size; Evergreen jacket services return ~265×400.
	 *
	 * DISABLED unless a jacket endpoint is explicitly configured on the
	 * widget (or via the chm_rss_jacket_base filter) — the plugin never
	 * contacts a third-party host the site owner didn't opt into.
	 *
	 * Cascade per item: jacket by ISBN → original feed thumbnail.
	 * Detection: Evergreen answers 200 for a real cover and 404 (with a
	 * 1×1 blank) when it has none. HEAD requests keep this cheap.
	 *
	 * Runs only on cache refresh, and under a total time budget so a slow
	 * catalog can never stall a page load — unresolved items simply keep
	 * their thumbnails until the next refresh.
	 *
	 * @param Feed_Item[] $items       Parsed items.
	 * @param string      $jacket_base Jacket endpoint base URL ('' = disabled).
	 * @return Feed_Item[]
	 */
	protected function resolve_hires_images( array $items, $jacket_base = '' ) {
		if ( ! apply_filters( 'chm_rss_resolve_hires', true ) ) {
			return $items;
		}

		$base = apply_filters( 'chm_rss_jacket_base', $jacket_base );
		if ( ! is_string( $base ) || '' === trim( $base ) ) {
			return $items;
		}
		$base = trailingslashit( trim( $base ) );

		$budget  = (float) apply_filters( 'chm_rss_hires_time_budget', 8.0 );
		$started = microtime( true );

		// Persistent per-ISBN results so the time budget is only spent on
		// unknown ISBNs. Hits are permanent; misses are re-checked weekly
		// (catalogs add covers over time). Coverage therefore accumulates
		// across refreshes even for 100+ item feeds.
		$cache_key = self::STALE_PREFIX . 'jackets_' . md5( $base );
		$cache     = get_option( $cache_key, [] );
		if ( ! is_array( $cache ) ) {
			$cache = [];
		}
		$dirty = false;

		foreach ( $items as $item ) {
			if ( '' === $item->isbn ) {
				continue;
			}

			$jacket = $base . rawurlencode( $item->isbn );

			if ( isset( $cache[ $item->isbn ] ) ) {
				$entry = $cache[ $item->isbn ];
				if ( ! empty( $entry['h'] ) ) {
					$item->image = esc_url_raw( $jacket, [ 'https' ] );
					continue;
				}
				if ( isset( $entry['t'] ) && ( time() - (int) $entry['t'] ) < WEEK_IN_SECONDS ) {
					continue; // Recent miss — don't re-check yet.
				}
			}

			if ( ( microtime( true ) - $started ) > $budget ) {
				continue; // Budget spent — unknown ISBNs wait for the next refresh.
			}

			$response = wp_safe_remote_head( $jacket, [ 'timeout' => 3 ] );
			if ( is_wp_error( $response ) ) {
				continue; // Network error — leave unknown, retry next refresh.
			}

			$hit                   = ( 200 === (int) wp_remote_retrieve_response_code( $response ) );
			$cache[ $item->isbn ] = [ 'h' => $hit ? 1 : 0, 't' => time() ];
			$dirty                 = true;

			if ( $hit ) {
				$item->image = esc_url_raw( $jacket, [ 'https' ] );
			}
		}

		if ( $dirty ) {
			// Keep the newest ~400 entries so the option can't grow unbounded.
			if ( count( $cache ) > 400 ) {
				uasort( $cache, static function ( $a, $b ) {
					return ( $b['t'] ?? 0 ) <=> ( $a['t'] ?? 0 );
				} );
				$cache = array_slice( $cache, 0, 400, true );
			}
			update_option( $cache_key, $cache, false ); // autoload: no.
		}

		return $items;
	}

	/**
	 * Discover the library catalog's Evergreen jacket endpoint from the
	 * feed's own item links (which redirect to the catalog).
	 *
	 * Process: follow up to 3 item links to their final host (skipping
	 * OverDrive/e-content hosts), then probe {host}/opac/extras/ac/jacket/large/
	 * with a known ISBN. 200 = Evergreen jacket service confirmed.
	 *
	 * The result — including failure — is cached per feed; failures are
	 * retried weekly. Failure simply means feed images are used, so this
	 * can never break the widget.
	 *
	 * @param Feed_Item[] $items    Parsed items.
	 * @param string      $feed_url Feed URL (cache key).
	 * @return string Jacket base URL, or '' when discovery failed.
	 */
	protected function discover_jacket_base( array $items, $feed_url ) {
		$opt    = self::STALE_PREFIX . 'discovery_' . md5( $feed_url );
		$cached = get_option( $opt );

		if ( is_array( $cached ) && isset( $cached['base'] ) ) {
			$fresh_failure = '' === $cached['base'] && ( time() - (int) ( $cached['t'] ?? 0 ) ) < WEEK_IN_SECONDS;
			if ( '' !== $cached['base'] || $fresh_failure ) {
				return (string) $cached['base'];
			}
		}

		$isbn = '';
		foreach ( $items as $item ) {
			if ( '' !== $item->isbn ) {
				$isbn = $item->isbn;
				break;
			}
		}

		$base = '';

		if ( '' !== $isbn ) {
			$tried = 0;
			foreach ( $items as $item ) {
				if ( '' === $item->link || $tried >= 3 ) {
					continue;
				}
				$tried++;

				$host_base = $this->resolve_final_base( $item->link );
				if ( '' === $host_base || false !== stripos( $host_base, 'overdrive' ) ) {
					continue;
				}

				$candidate = $host_base . 'opac/extras/ac/jacket/large/';
				$response  = wp_safe_remote_head( $candidate . rawurlencode( $isbn ), [ 'timeout' => 4 ] );

				if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
					$base = $candidate;
					break;
				}
			}
		}

		update_option( $opt, [ 'base' => $base, 't' => time() ], false );

		return $base;
	}

	/**
	 * Follow redirects manually (max 3 hops) and return the final
	 * scheme://host/ of a URL, or '' on failure.
	 *
	 * @param string $url Starting URL.
	 * @return string
	 */
	protected function resolve_final_base( $url ) {
		for ( $hop = 0; $hop < 3; $hop++ ) {
			$response = wp_safe_remote_get(
				$url,
				[
					'timeout'     => 5,
					'redirection' => 0,
					'user-agent'  => apply_filters(
						'chm_rss_user_agent',
						'CHM-RSS-Display/' . CHM_RSS_VERSION . '; +' . home_url( '/' )
					),
				]
			);

			if ( is_wp_error( $response ) ) {
				return '';
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( $code < 300 || $code >= 400 ) {
				break;
			}

			$location = wp_remote_retrieve_header( $response, 'location' );
			if ( is_array( $location ) ) {
				$location = end( $location );
			}
			if ( ! $location ) {
				return '';
			}

			// Resolve relative redirects against the current URL's host.
			if ( 0 !== strpos( $location, 'http' ) ) {
				$parts = wp_parse_url( $url );
				if ( empty( $parts['host'] ) ) {
					return '';
				}
				$location = ( $parts['scheme'] ?? 'https' ) . '://' . $parts['host'] . '/' . ltrim( $location, '/' );
			}

			$url = $location;
		}

		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) ) {
			return '';
		}

		return ( $parts['scheme'] ?? 'https' ) . '://' . $parts['host'] . '/';
	}

	/**
	 * Rebuild Feed_Item objects from cached arrays.
	 *
	 * @param array $rows Arrays of item properties.
	 * @return Feed_Item[]
	 */
	protected function hydrate( array $rows ) {
		$items = [];
		foreach ( $rows as $row ) {
			if ( is_array( $row ) ) {
				$items[] = new Feed_Item( $row );
			}
		}
		return $items;
	}

	/**
	 * Whether we're rendering inside the Elementor editor/preview.
	 *
	 * @return bool
	 */
	protected function is_elementor_editor() {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return false;
		}
		$elementor = \Elementor\Plugin::$instance;

		return ( $elementor->editor && $elementor->editor->is_edit_mode() )
			|| ( $elementor->preview && $elementor->preview->is_preview_mode() );
	}
}
