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
	const REGISTRY_OPTION  = 'chm_rss_cache_keys';
	const DEFAULT_TTL      = 6 * HOUR_IN_SECONDS;
	const EDITOR_TTL       = 60;

	/**
	 * Get parsed feed items, served from cache when possible.
	 *
	 * @param string $url Feed URL (https).
	 * @param int    $ttl Cache TTL in seconds. 0/negative falls back to default.
	 * @return Feed_Item[] Possibly empty array. Never WP_Error — errors degrade to stale or [].
	 */
	public function get_items( $url, $ttl = 0 ) {
		$url = esc_url_raw( trim( (string) $url ), [ 'https', 'http' ] );
		if ( '' === $url ) {
			return [];
		}

		$ttl = (int) apply_filters( 'chm_rss_cache_ttl', $ttl > 0 ? $ttl : self::DEFAULT_TTL, $url );

		// In the Elementor editor, keep the cache very short so editors see fresh data.
		if ( $this->is_elementor_editor() ) {
			$ttl = min( $ttl, self::EDITOR_TTL );
		}

		// Version-scoped: a plugin update invalidates cached parses, so a
		// parser fix is never trapped behind a stale transient for hours.
		$key    = md5( CHM_RSS_VERSION . '|' . $url );
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

		$items = $this->parse( $xml );
		$items = $this->resolve_hires_images( $items );
		$raw   = array_map( 'get_object_vars', $items );

		set_transient( self::TRANSIENT_PREFIX . $key, $raw, $ttl );
		$this->remember_key( $key );

		if ( ! empty( $raw ) ) {
			update_option( self::STALE_PREFIX . $key, $raw, false ); // autoload: no.
		}

		return $items;
	}

	/**
	 * Delete every cached feed (transients + stale copies).
	 * Backs the admin-bar "Refresh RSS Feed" action.
	 */
	public static function flush() {
		$keys = get_option( self::REGISTRY_OPTION, [] );
		foreach ( array_filter( (array) $keys, 'is_string' ) as $key ) {
			delete_transient( self::TRANSIENT_PREFIX . $key );
			delete_option( self::STALE_PREFIX . $key );
		}
		delete_option( self::REGISTRY_OPTION );
	}

	/**
	 * Track active cache keys so flush() works on external object caches
	 * (Redis/Memcached), where transients never appear in wp_options.
	 *
	 * @param string $key Cache key.
	 */
	protected function remember_key( $key ) {
		$keys = get_option( self::REGISTRY_OPTION, [] );
		$keys = is_array( $keys ) ? $keys : [];
		if ( ! in_array( $key, $keys, true ) ) {
			$keys[] = $key;
			update_option( self::REGISTRY_OPTION, $keys, false );
		}
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

			// The ISBN lives in the original Wowbrary link's i= param —
			// extract it before clean_link() swaps in the direct URL.
			$isbn = $this->extract_isbn( $link );
			$link = $this->clean_link( $link );

			// The feed embeds a "<span class='NUEITEM'>Downloadable Audio</span>"
			// format chip inside the description. Drop it wholesale — otherwise
			// tag-stripping later leaks its text into the blurb.
			$raw_desc = preg_replace(
				'#<span[^>]*NUEITEM[^>]*>.*?</span>#is',
				'',
				$this->node_text( $node, 'description' )
			);

			list( $author, $description ) = $this->split_author( $raw_desc );

			$items[] = new Feed_Item(
				[
					'title'       => sanitize_text_field( $title ),
					'author'      => sanitize_text_field( $author ),
					'description' => sanitize_text_field( $description ),
					'link'        => $link,
					'image'       => $this->extract_image( $node ),
					'timestamp'   => $this->parse_date( $this->node_text( $node, 'pubDate' ) ),
					'category'    => sanitize_text_field( $this->node_text( $node, 'category' ) ),
					'isbn'        => $isbn,
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

		// Find the first ". " that isn't part of an initial — "By H. M. Wolfe."
		// must split after "Wolfe", not after "H".
		$pos    = false;
		$offset = 0;
		while ( false !== ( $found = strpos( $description, '. ', $offset ) ) ) {
			$last_word = preg_replace( '/^.*\s/s', '', substr( $description, 0, $found ) );
			if ( preg_match( '/^[A-Z]$/', $last_word ) ) {
				$offset = $found + 2;
				continue;
			}
			$pos = $found;
			break;
		}

		if ( false === $pos ) {
			return [ '', $description ];
		}

		$candidate = substr( $description, 0, $pos );
		$rest      = trim( substr( $description, $pos + 2 ) );

		// A real author prefix is short; long prefixes are just the first sentence.
		if ( '' === $candidate || strlen( $candidate ) > 60 || str_word_count( $candidate ) > 6 ) {
			return [ '', $description ];
		}

		// The feed sometimes prefixes audio items with "By Author Name".
		$candidate = preg_replace( '/^by\s+/i', '', $candidate );

		return [ $candidate, $rest ];
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
	 * Clean an outbound item link.
	 *
	 * Wowbrary links route through its l.aspx redirector with tracking-ish
	 * params (u=, t=, rss). The c= param encodes the real destination:
	 * numeric → Evergreen/VuFind catalog record, "WOV{n}" → OverDrive media.
	 * Linking straight there drops the extra hop and params, and the
	 * catalog sees this site as the traffic source. Unknown shapes keep
	 * the (https-upgraded) original link.
	 *
	 * @param string $link Item link from the feed.
	 * @return string
	 */
	protected function clean_link( $link ) {
		// The feed emits plain http; every destination serves https.
		$link = preg_replace( '#^http://#i', 'https://', (string) $link );

		if ( ! apply_filters( 'chm_rss_direct_links', true ) || false === stripos( $link, 'wowbrary.org' ) ) {
			return $link;
		}

		$query = wp_parse_url( $link, PHP_URL_QUERY );
		if ( ! $query ) {
			return $link;
		}
		parse_str( $query, $params );
		$record = isset( $params['c'] ) ? (string) $params['c'] : '';

		if ( '' !== $record && ctype_digit( $record ) ) {
			$base = apply_filters( 'chm_rss_catalog_record_base', 'https://belchertwn.cwmars.org/Record/' );
			return $base ? esc_url_raw( $base . $record, [ 'https' ] ) : $link;
		}

		if ( preg_match( '/^WOV(\d+)$/', $record, $m ) ) {
			$base = apply_filters( 'chm_rss_econtent_base', 'https://cwmars.overdrive.com/media/' );
			return $base ? esc_url_raw( $base . $m[1], [ 'https' ] ) : $link;
		}

		return $link;
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
	 * Upgrade item images to the CW Mars (Evergreen) licensed cover jackets
	 * where available. Wowbrary thumbnails are only 160px and blur when
	 * rendered at card size; the consortium jacket service returns ~265×400.
	 *
	 * Cascade per item: CW Mars jacket by ISBN → original Wowbrary thumbnail.
	 * Detection: the jacket endpoint answers 200 for a real cover and 404
	 * (with a 1×1 blank) when it has none. HEAD requests keep this cheap.
	 *
	 * Runs only on cache refresh, and under a total time budget so a slow
	 * catalog can never stall a page load — unresolved items simply keep
	 * their thumbnails until the next refresh.
	 *
	 * @param Feed_Item[] $items Parsed items.
	 * @return Feed_Item[]
	 */
	protected function resolve_hires_images( array $items ) {
		if ( ! apply_filters( 'chm_rss_resolve_hires', true ) ) {
			return $items;
		}

		$base = apply_filters(
			'chm_rss_jacket_base',
			'https://bark.cwmars.org/opac/extras/ac/jacket/large/'
		);
		if ( '' === $base ) {
			return $items;
		}

		$budget  = (float) apply_filters( 'chm_rss_hires_time_budget', 8.0 );
		$started = microtime( true );

		foreach ( $items as $item ) {
			if ( '' === $item->isbn ) {
				continue;
			}
			if ( ( microtime( true ) - $started ) > $budget ) {
				break;
			}

			$jacket   = $base . rawurlencode( $item->isbn );
			$response = wp_safe_remote_head( $jacket, [ 'timeout' => 3 ] );

			if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
				$item->image = esc_url_raw( $jacket, [ 'https' ] );
			}
		}

		return $items;
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
