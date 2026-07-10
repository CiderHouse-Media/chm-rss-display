<?php
namespace ChmRss;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin controller. Only instantiated when Elementor is active
 * and meets the minimum version (see chm-rss-display.php bootstrap).
 */
final class Plugin {

	/** @var Plugin|null */
	private static $instance = null;

	/**
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );
		add_action( 'elementor/elements/categories_registered', [ $this, 'register_category' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );

		// The editor preview iframe also needs the frontend assets registered.
		add_action( 'elementor/preview/enqueue_styles', [ $this, 'register_assets' ] );

		// Admin-bar "Refresh RSS Feed" — forces a fresh fetch on demand.
		add_action( 'admin_bar_menu', [ $this, 'add_refresh_node' ], 90 );
		add_action( 'template_redirect', [ $this, 'maybe_flush_cache' ] );
	}

	/**
	 * Front-end admin-bar node that flushes the feed cache.
	 *
	 * @param \WP_Admin_Bar $bar Admin bar instance.
	 */
	public function add_refresh_node( $bar ) {
		if ( is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$bar->add_node(
			[
				'id'    => 'chm-rss-refresh',
				'title' => esc_html__( 'Refresh RSS Feed', 'chm-rss-display' ),
				'href'  => wp_nonce_url( add_query_arg( 'chm_rss_flush', '1' ), 'chm_rss_flush' ),
				'meta'  => [
					'title' => esc_attr__( 'Clear the cached feed and refetch it now', 'chm-rss-display' ),
				],
			]
		);
	}

	/**
	 * Handle the admin-bar flush action, then reload the page clean.
	 */
	public function maybe_flush_cache() {
		if ( ! isset( $_GET['chm_rss_flush'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'chm_rss_flush' );

		Feed_Fetcher::flush();

		wp_safe_redirect( remove_query_arg( [ 'chm_rss_flush', '_wpnonce' ] ) );
		exit;
	}

	/**
	 * Register the widget with Elementor.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 */
	public function register_widgets( $widgets_manager ) {
		require_once CHM_RSS_PATH . 'widgets/class-rss-feed-widget.php';
		$widgets_manager->register( new \ChmRss\Widgets\Rss_Feed_Widget() );
	}

	/**
	 * Add a "Cider House" panel category so client widgets are easy to find.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager.
	 */
	public function register_category( $elements_manager ) {
		$elements_manager->add_category(
			'cider-house',
			[
				'title' => esc_html__( 'Cider House', 'chm-rss-display' ),
				'icon'  => 'fa fa-book',
			]
		);
	}

	/**
	 * Register (not enqueue) widget assets. Elementor loads them
	 * on demand via get_style_depends() / get_script_depends().
	 */
	public function register_assets() {
		wp_register_style(
			'chm-rss-widget',
			CHM_RSS_URL . 'assets/css/chm-rss-widget.css',
			[],
			CHM_RSS_VERSION
		);

		// Depend on Elementor's bundled Swiper; fall back gracefully if the
		// handle is absent (very old Elementor) — the JS also guards at runtime.
		$deps = [ 'jquery' ];
		if ( wp_script_is( 'swiper', 'registered' ) ) {
			$deps[] = 'swiper';
		}

		wp_register_script(
			'chm-rss-widget',
			CHM_RSS_URL . 'assets/js/chm-rss-widget.js',
			$deps,
			CHM_RSS_VERSION,
			true
		);
	}
}
