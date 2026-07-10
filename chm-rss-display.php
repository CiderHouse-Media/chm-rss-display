<?php
/**
 * Plugin Name:       CHM RSS Display
 * Plugin URI:        https://github.com/CiderHouse-Media/chm-rss-display
 * Description:       Elementor widget that displays an external RSS feed (Wowbrary / CW Mars) as a carousel, grid, or list. Display only — never imports posts.
 * Version:           1.0.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Cider House Media
 * Author URI:        https://ciderhouse.media
 * License:           GPL-2.0-or-later
 * Text Domain:       chm-rss-display
 *
 * Elementor tested up to: 3.30
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CHM_RSS_VERSION', '1.0.1' );
define( 'CHM_RSS_FILE', __FILE__ );
define( 'CHM_RSS_PATH', plugin_dir_path( __FILE__ ) );
define( 'CHM_RSS_URL', plugin_dir_url( __FILE__ ) );
define( 'CHM_RSS_MIN_ELEMENTOR', '3.20.0' );

require_once CHM_RSS_PATH . 'includes/class-feed-item.php';
require_once CHM_RSS_PATH . 'includes/class-feed-fetcher.php';
require_once CHM_RSS_PATH . 'includes/class-plugin.php';

/**
 * Boot the plugin once all plugins are loaded so we can check for Elementor.
 */
function chm_rss_display_boot() {

	// Elementor missing entirely.
	if ( ! did_action( 'elementor/loaded' ) ) {
		add_action( 'admin_notices', 'chm_rss_display_notice_missing_elementor' );
		return;
	}

	// Elementor too old.
	if ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, CHM_RSS_MIN_ELEMENTOR, '<' ) ) {
		add_action( 'admin_notices', 'chm_rss_display_notice_elementor_version' );
		return;
	}

	\ChmRss\Plugin::instance();
}
add_action( 'plugins_loaded', 'chm_rss_display_boot' );

/**
 * Admin notice: Elementor not active.
 */
function chm_rss_display_notice_missing_elementor() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html__( 'CHM RSS Display requires Elementor to be installed and activated.', 'chm-rss-display' )
	);
}

/**
 * Admin notice: Elementor version too old.
 */
function chm_rss_display_notice_elementor_version() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		sprintf(
			/* translators: %s: minimum Elementor version */
			esc_html__( 'CHM RSS Display requires Elementor version %s or greater.', 'chm-rss-display' ),
			esc_html( CHM_RSS_MIN_ELEMENTOR )
		)
	);
}
