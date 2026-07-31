<?php
namespace ChmRss;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Value object representing one parsed feed item.
 * All properties are sanitized at parse time by Feed_Fetcher.
 */
class Feed_Item {

	/** @var string */
	public $title = '';

	/** @var string Author derived from the description prefix ("Author. Description…"). May be empty. */
	public $author = '';

	/** @var string Description body with the author prefix removed. Plain text. */
	public $description = '';

	/** @var string Click-through URL (Wowbrary redirect to CW Mars). */
	public $link = '';

	/** @var string Cover image URL extracted from content:encoded. May be empty. */
	public $image = '';

	/** @var string Original feed thumbnail, kept when a hi-res cover replaces $image — the browser falls back to it if the hi-res host fails. May be empty. */
	public $thumb = '';

	/** @var int|null Unix timestamp, or null when the pubDate could not be parsed. */
	public $timestamp = null;

	/** @var string Feed category (e.g. "Top Choices"). May be empty. */
	public $category = '';

	/** @var string ISBN/EAN extracted from the item link's i= parameter. May be empty. */
	public $isbn = '';

	/**
	 * @param array $data Associative array of properties.
	 */
	public function __construct( array $data = [] ) {
		foreach ( $data as $key => $value ) {
			if ( property_exists( $this, $key ) ) {
				$this->$key = $value;
			}
		}
	}

	/**
	 * Whether this item has a usable cover image.
	 *
	 * @return bool
	 */
	public function has_image() {
		return '' !== $this->image;
	}
}
