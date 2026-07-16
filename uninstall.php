<?php
/**
 * Uninstall handler for CHM RSS Display.
 *
 * Removes all plugin data: feed transients and the stale-copy options.
 * Runs only when the plugin is deleted through the WordPress admin.
 *
 * @package chm-rss-display
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Feed caches are keyed as chm_rss_{md5} (transients) and chm_rss_stale_{md5} (options).
// Transients live in options as _transient_chm_rss_* / _transient_timeout_chm_rss_*.
$like_patterns = [
	$wpdb->esc_like( '_transient_chm_rss_' ) . '%',
	$wpdb->esc_like( '_transient_timeout_chm_rss_' ) . '%',
	$wpdb->esc_like( 'chm_rss_stale_' ) . '%',
];

delete_option( 'chm_rss_feeds' );

foreach ( $like_patterns as $pattern ) {
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- uninstall cleanup of dynamically named keys.
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$pattern
		)
	);
}
