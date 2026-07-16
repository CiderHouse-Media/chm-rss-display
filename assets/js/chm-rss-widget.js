/**
 * CHM RSS Display — carousel init.
 * Uses Elementor's bundled Swiper. Registers as an Elementor frontend
 * handler so the carousel re-initializes live in the editor.
 */
( function () {
	'use strict';

	var BREAK_TABLET = 768;   // Elementor default: tablet starts at 768px.
	var BREAK_DESKTOP = 1025; // Elementor default: desktop starts at 1025px.

	function parseSettings( el ) {
		try {
			return JSON.parse( el.getAttribute( 'data-settings' ) ) || {};
		} catch ( e ) {
			return {};
		}
	}

	function buildConfig( wrap, el, s ) {
		var reducedMotion = window.matchMedia &&
			window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

		var config = {
			// Mobile-first: base = mobile, breakpoints scale up.
			slidesPerView: s.slidesToShowMobile || 1,
			slidesPerGroup: 1,
			spaceBetween: typeof s.spaceBetweenMobile === 'number' ? s.spaceBetweenMobile : 16,
			speed: s.speed || 400,
			loop: !! s.loop,
			watchOverflow: true,
			breakpoints: {},
			a11y: { enabled: true }
		};

		config.breakpoints[ BREAK_TABLET ] = {
			slidesPerView: s.slidesToShowTablet || 2,
			spaceBetween: typeof s.spaceBetweenTablet === 'number' ? s.spaceBetweenTablet : 24
		};
		config.breakpoints[ BREAK_DESKTOP ] = {
			slidesPerView: s.slidesToShow || 4,
			slidesPerGroup: s.slidesToScroll || 1,
			spaceBetween: typeof s.spaceBetween === 'number' ? s.spaceBetween : 24
		};

		if ( s.autoplay && ! reducedMotion ) {
			config.autoplay = {
				delay: s.autoplayDelay || 5000,
				disableOnInteraction: false
			};
		}

		if ( s.dots ) {
			var dots = el.querySelector( '.chm-rss__dots' );
			if ( dots ) {
				config.pagination = { el: dots, clickable: true };
			}
		}

		if ( s.arrows ) {
			var prev = wrap.querySelector( '.chm-rss__arrow--prev' );
			var next = wrap.querySelector( '.chm-rss__arrow--next' );
			if ( prev && next ) {
				config.navigation = { prevEl: prev, nextEl: next };
			}
		}

		return config;
	}

	function bindHoverPause( wrap, swiper, s ) {
		if ( ! s.autoplay || ! s.pauseOnHover || ! swiper.autoplay ) {
			return;
		}
		wrap.addEventListener( 'mouseenter', function () {
			if ( swiper.autoplay && swiper.autoplay.stop ) {
				swiper.autoplay.stop();
			}
		} );
		wrap.addEventListener( 'mouseleave', function () {
			if ( swiper.autoplay && swiper.autoplay.start ) {
				swiper.autoplay.start();
			}
		} );
	}

	function initCarousel( el ) {
		var wrap = el.closest( '.chm-rss__carousel-wrap' ) || el.parentNode;
		var settings = parseSettings( el );
		var config = buildConfig( wrap, el, settings );

		// Editor re-renders create fresh DOM, but guard against double init.
		if ( el.swiper && el.swiper.destroy ) {
			el.swiper.destroy( true, true );
		}

		if ( typeof window.Swiper === 'function' ) {
			var instance = new window.Swiper( el, config );
			bindHoverPause( wrap, instance, settings );
			return;
		}

		// Older Elementor lazy-loads Swiper through an async utility wrapper.
		if ( window.elementorFrontend && elementorFrontend.utils && elementorFrontend.utils.swiper ) {
			new elementorFrontend.utils.swiper( jQuery( el ), config ).then( function ( instance ) {
				bindHoverPause( wrap, instance, settings );
			} );
		}
	}

	function initLoadMore( scope ) {
		var buttons = scope.querySelectorAll( '.chm-rss__more' );
		for ( var i = 0; i < buttons.length; i++ ) {
			(function ( btn ) {
				btn.addEventListener( 'click', function () {
					var root = btn.closest( '.chm-rss' );
					if ( ! root ) {
						return;
					}
					var hidden = root.querySelectorAll( '.chm-rss-card--hidden' );
					var batch = parseInt( btn.getAttribute( 'data-batch' ), 10 ) || 12;
					for ( var j = 0; j < hidden.length && j < batch; j++ ) {
						hidden[ j ].classList.remove( 'chm-rss-card--hidden' );
					}
					if ( ! root.querySelector( '.chm-rss-card--hidden' ) ) {
						btn.parentNode.style.display = 'none';
					}
				} );
			})( buttons[ i ] );
		}
	}

	function initScope( scope ) {
		var carousels = scope.querySelectorAll( '.chm-rss__carousel' );
		for ( var i = 0; i < carousels.length; i++ ) {
			initCarousel( carousels[ i ] );
		}
		initLoadMore( scope );
	}

	// Elementor context (frontend + editor live preview).
	window.addEventListener( 'elementor/frontend/init', function () {
		elementorFrontend.hooks.addAction(
			'frontend/element_ready/chm_rss_feed.default',
			function ( $scope ) {
				initScope( $scope[ 0 ] );
			}
		);
	} );

	// Non-Elementor fallback (e.g. widget markup cached into a non-Elementor context).
	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! window.elementorFrontend ) {
			initScope( document );
		}
	} );
} )();
