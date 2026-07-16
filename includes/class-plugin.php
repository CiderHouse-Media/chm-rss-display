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

		// Register after Elementor registers its own assets so the 'swiper'
		// handles exist — hooking plain wp_enqueue_scripts races Elementor
		// and can silently drop the Swiper dependency on the frontend.
		add_action( 'elementor/frontend/after_register_scripts', [ $this, 'register_assets' ] );
		add_action( 'elementor/frontend/after_register_styles', [ $this, 'register_assets' ] );

		// The editor preview iframe also needs the frontend assets registered.
		add_action( 'elementor/preview/enqueue_styles', [ $this, 'register_assets' ] );

		// Safety net for contexts where the Elementor hooks don't fire.
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ], 20 );
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
	 * Register (not enqueue) widget assets. Elementor loads them on demand
	 * via get_style_depends() / get_script_depends(). Idempotent — this is
	 * hooked in several places to cover frontend, editor, and fallbacks.
	 */
	public function register_assets() {
		if ( ! wp_style_is( 'chm-rss-widget', 'registered' ) ) {
			wp_register_style(
				'chm-rss-widget',
				CHM_RSS_URL . 'assets/css/chm-rss-widget.css',
				[],
				CHM_RSS_VERSION
			);
		}

		if ( ! wp_script_is( 'chm-rss-widget', 'registered' ) ) {
			// Deliberately NOT listing 'swiper' as a hard dependency: an
			// unmet script dep silently blocks the whole script. Load order
			// is guaranteed by the widget's get_script_depends() instead,
			// and the JS degrades gracefully if Swiper is absent.
			wp_register_script(
				'chm-rss-widget',
				CHM_RSS_URL . 'assets/js/chm-rss-widget.js',
				[ 'jquery' ],
				CHM_RSS_VERSION,
				true
			);
		}
	}
}
