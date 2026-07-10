<?php
namespace ChmRss\Widgets;

use ChmRss\Feed_Fetcher;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * RSS Book Feed widget — carousel / grid / list views of an external
 * RSS feed (Wowbrary → CW Mars). Display only.
 */
class Rss_Feed_Widget extends Widget_Base {

	public function get_name(): string {
		return 'chm_rss_feed';
	}

	public function get_title(): string {
		return esc_html__( 'RSS Book Feed', 'chm-rss-display' );
	}

	public function get_icon(): string {
		return 'eicon-posts-carousel';
	}

	public function get_categories(): array {
		return [ 'cider-house', 'general' ];
	}

	public function get_keywords(): array {
		return [ 'rss', 'feed', 'books', 'library', 'carousel', 'wowbrary' ];
	}

	public function get_style_depends(): array {
		return [ 'chm-rss-widget' ];
	}

	public function get_script_depends(): array {
		return [ 'chm-rss-widget' ];
	}

	protected function is_dynamic_content(): bool {
		return true; // Remote data — never let Elementor's element cache freeze it.
	}

	/* ---------------------------------------------------------------------
	 * Controls
	 * ------------------------------------------------------------------- */

	protected function register_controls(): void {
		$this->register_feed_section();
		$this->register_layout_section();
		$this->register_card_content_section();
		$this->register_carousel_section();

		$this->register_style_card();
		$this->register_style_image();
		$this->register_style_text();
		$this->register_style_cta();
		$this->register_style_nav();
	}

	private function register_feed_section(): void {
		$this->start_controls_section(
			'section_feed',
			[ 'label' => esc_html__( 'Feed', 'chm-rss-display' ), 'tab' => Controls_Manager::TAB_CONTENT ]
		);

		$this->add_control(
			'feed_url',
			[
				'label'         => esc_html__( 'Feed URL', 'chm-rss-display' ),
				'type'          => Controls_Manager::URL,
				'placeholder'   => 'https://wowbrary.org/rss.aspx?l=9159&c=GEN',
				'options'       => false,
				'dynamic'       => [ 'active' => true ],
				'label_block'   => true,
			]
		);

		$this->add_control(
			'items_count',
			[
				'label'   => esc_html__( 'Items to Show', 'chm-rss-display' ),
				'type'    => Controls_Manager::SLIDER,
				'range'   => [ 'px' => [ 'min' => 1, 'max' => 20 ] ],
				'default' => [ 'size' => 10 ],
			]
		);

		$this->add_control(
			'cache_ttl',
			[
				'label'   => esc_html__( 'Cache Duration', 'chm-rss-display' ),
				'type'    => Controls_Manager::SELECT,
				'default' => (string) ( 6 * HOUR_IN_SECONDS ),
				'options' => [
					(string) HOUR_IN_SECONDS          => esc_html__( '1 hour', 'chm-rss-display' ),
					(string) ( 6 * HOUR_IN_SECONDS )  => esc_html__( '6 hours', 'chm-rss-display' ),
					(string) ( 12 * HOUR_IN_SECONDS ) => esc_html__( '12 hours', 'chm-rss-display' ),
					(string) DAY_IN_SECONDS           => esc_html__( '24 hours', 'chm-rss-display' ),
				],
			]
		);

		$this->add_control(
			'new_tab',
			[
				'label'       => esc_html__( 'Open Links in New Tab', 'chm-rss-display' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => 'yes',
				'description' => esc_html__( 'Items link off-site to the library catalog (CW Mars).', 'chm-rss-display' ),
			]
		);

		$this->end_controls_section();
	}

	private function register_layout_section(): void {
		$this->start_controls_section(
			'section_layout',
			[ 'label' => esc_html__( 'Layout', 'chm-rss-display' ), 'tab' => Controls_Manager::TAB_CONTENT ]
		);

		$this->add_control(
			'view',
			[
				'label'   => esc_html__( 'View', 'chm-rss-display' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'carousel',
				'options' => [
					'carousel' => esc_html__( 'Carousel', 'chm-rss-display' ),
					'grid'     => esc_html__( 'Grid', 'chm-rss-display' ),
					'list'     => esc_html__( 'List', 'chm-rss-display' ),
				],
			]
		);

		$this->add_responsive_control(
			'columns',
			[
				'label'          => esc_html__( 'Columns', 'chm-rss-display' ),
				'type'           => Controls_Manager::SLIDER,
				'range'          => [ 'px' => [ 'min' => 1, 'max' => 6 ] ],
				'default'        => [ 'size' => 4 ],
				'tablet_default' => [ 'size' => 2 ],
				'mobile_default' => [ 'size' => 1 ],
				'condition'      => [ 'view' => 'grid' ],
				'selectors'      => [
					'{{WRAPPER}} .chm-rss__grid' => 'grid-template-columns: repeat({{SIZE}}, 1fr);',
				],
			]
		);

		$this->add_responsive_control(
			'item_gap',
			[
				'label'          => esc_html__( 'Item Gap', 'chm-rss-display' ),
				'type'           => Controls_Manager::SLIDER,
				'size_units'     => [ 'px' ],
				'range'          => [ 'px' => [ 'min' => 0, 'max' => 80 ] ],
				'default'        => [ 'size' => 24 ],
				'mobile_default' => [ 'size' => 16 ],
				'condition'      => [ 'view' => [ 'grid', 'list' ] ],
				'selectors'      => [
					'{{WRAPPER}} .chm-rss__grid' => 'gap: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .chm-rss__list' => 'gap: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'list_image_position',
			[
				'label'     => esc_html__( 'Image Position', 'chm-rss-display' ),
				'type'      => Controls_Manager::CHOOSE,
				'default'   => 'left',
				'options'   => [
					'left'  => [ 'title' => esc_html__( 'Left', 'chm-rss-display' ), 'icon' => 'eicon-h-align-left' ],
					'right' => [ 'title' => esc_html__( 'Right', 'chm-rss-display' ), 'icon' => 'eicon-h-align-right' ],
				],
				'condition' => [ 'view' => 'list' ],
				'selectors' => [
					'{{WRAPPER}} .chm-rss-card--row' => 'flex-direction: {{VALUE}};',
				],
				'selectors_dictionary' => [
					'left'  => 'row',
					'right' => 'row-reverse',
				],
			]
		);

		$this->end_controls_section();
	}

	private function register_card_content_section(): void {
		$this->start_controls_section(
			'section_card_content',
			[ 'label' => esc_html__( 'Card Content', 'chm-rss-display' ), 'tab' => Controls_Manager::TAB_CONTENT ]
		);

		$this->add_control(
			'heading_text',
			[
				'label'       => esc_html__( 'Section Heading', 'chm-rss-display' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'New Arrivals', 'chm-rss-display' ),
				'description' => esc_html__( 'Leave empty to hide the heading.', 'chm-rss-display' ),
				'dynamic'     => [ 'active' => true ],
			]
		);

		$this->add_control(
			'show_image',
			[
				'label'   => esc_html__( 'Show Cover Image', 'chm-rss-display' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			]
		);

		$this->add_control(
			'show_category',
			[
				'label'   => esc_html__( 'Show Category Label', 'chm-rss-display' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			]
		);

		$this->add_control(
			'show_author',
			[
				'label'   => esc_html__( 'Show Author', 'chm-rss-display' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			]
		);

		$this->add_control(
			'show_description',
			[
				'label'   => esc_html__( 'Show Description', 'chm-rss-display' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			]
		);

		$this->add_control(
			'desc_length',
			[
				'label'     => esc_html__( 'Description Length (words)', 'chm-rss-display' ),
				'type'      => Controls_Manager::NUMBER,
				'default'   => 25,
				'min'       => 5,
				'max'       => 100,
				'condition' => [ 'show_description' => 'yes' ],
			]
		);

		$this->add_control(
			'show_date',
			[
				'label'   => esc_html__( 'Show Date', 'chm-rss-display' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => '',
			]
		);

		$this->add_control(
			'show_cta',
			[
				'label'   => esc_html__( 'Show "Read More"', 'chm-rss-display' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			]
		);

		$this->add_control(
			'cta_text',
			[
				'label'     => esc_html__( 'Read More Text', 'chm-rss-display' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => esc_html__( 'View at Library', 'chm-rss-display' ),
				'condition' => [ 'show_cta' => 'yes' ],
			]
		);

		$this->add_control(
			'cta_style',
			[
				'label'     => esc_html__( 'Read More Style', 'chm-rss-display' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'button',
				'options'   => [
					'button' => esc_html__( 'Button', 'chm-rss-display' ),
					'link'   => esc_html__( 'Link', 'chm-rss-display' ),
				],
				'condition' => [ 'show_cta' => 'yes' ],
			]
		);

		$this->add_control(
			'title_tag',
			[
				'label'   => esc_html__( 'Title HTML Tag', 'chm-rss-display' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'h3',
				'options' => [
					'h2'  => 'H2',
					'h3'  => 'H3',
					'h4'  => 'H4',
					'h5'  => 'H5',
					'h6'  => 'H6',
					'div' => 'div',
				],
			]
		);

		$this->end_controls_section();
	}

	private function register_carousel_section(): void {
		$this->start_controls_section(
			'section_carousel',
			[
				'label'     => esc_html__( 'Carousel Settings', 'chm-rss-display' ),
				'tab'       => Controls_Manager::TAB_CONTENT,
				'condition' => [ 'view' => 'carousel' ],
			]
		);

		$this->add_responsive_control(
			'slides_to_show',
			[
				'label'          => esc_html__( 'Slides to Show', 'chm-rss-display' ),
				'type'           => Controls_Manager::SLIDER,
				'range'          => [ 'px' => [ 'min' => 1, 'max' => 6 ] ],
				'default'        => [ 'size' => 4 ],
				'tablet_default' => [ 'size' => 2 ],
				'mobile_default' => [ 'size' => 1 ],
			]
		);

		$this->add_control(
			'slides_to_scroll',
			[
				'label'   => esc_html__( 'Slides to Scroll', 'chm-rss-display' ),
				'type'    => Controls_Manager::SLIDER,
				'range'   => [ 'px' => [ 'min' => 1, 'max' => 6 ] ],
				'default' => [ 'size' => 1 ],
			]
		);

		$this->add_responsive_control(
			'space_between',
			[
				'label'          => esc_html__( 'Space Between Slides', 'chm-rss-display' ),
				'type'           => Controls_Manager::SLIDER,
				'size_units'     => [ 'px' ],
				'range'          => [ 'px' => [ 'min' => 0, 'max' => 80 ] ],
				'default'        => [ 'size' => 24 ],
				'mobile_default' => [ 'size' => 16 ],
			]
		);

		$this->add_control(
			'autoplay',
			[
				'label'   => esc_html__( 'Autoplay', 'chm-rss-display' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			]
		);

		$this->add_control(
			'autoplay_delay',
			[
				'label'     => esc_html__( 'Autoplay Delay (ms)', 'chm-rss-display' ),
				'type'      => Controls_Manager::NUMBER,
				'default'   => 5000,
				'min'       => 1000,
				'step'      => 500,
				'condition' => [ 'autoplay' => 'yes' ],
			]
		);

		$this->add_control(
			'pause_on_hover',
			[
				'label'     => esc_html__( 'Pause on Hover', 'chm-rss-display' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => 'yes',
				'condition' => [ 'autoplay' => 'yes' ],
			]
		);

		$this->add_control(
			'infinite_loop',
			[
				'label'       => esc_html__( 'Infinite Loop', 'chm-rss-display' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => '',
				'description' => esc_html__( 'The approved design uses edge-stopped navigation (arrows dim at the ends).', 'chm-rss-display' ),
			]
		);

		$this->add_control(
			'show_arrows',
			[
				'label'   => esc_html__( 'Arrows', 'chm-rss-display' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			]
		);

		$this->add_control(
			'show_dots',
			[
				'label'   => esc_html__( 'Dots', 'chm-rss-display' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			]
		);

		$this->add_control(
			'transition_speed',
			[
				'label'   => esc_html__( 'Transition Speed (ms)', 'chm-rss-display' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 400,
				'min'     => 100,
				'step'    => 50,
			]
		);

		$this->end_controls_section();
	}

	/* ------------------------------ Style tab --------------------------- */

	private function register_style_card(): void {
		$this->start_controls_section(
			'section_style_card',
			[ 'label' => esc_html__( 'Card', 'chm-rss-display' ), 'tab' => Controls_Manager::TAB_STYLE ]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name'     => 'card_background',
				'types'    => [ 'classic', 'gradient' ],
				'selector' => '{{WRAPPER}} .chm-rss-card',
				'fields_options' => [
					'background' => [ 'default' => 'classic' ],
					'color'      => [ 'default' => '#FFFFFF' ],
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name'     => 'card_border',
				'selector' => '{{WRAPPER}} .chm-rss-card',
			]
		);

		$this->add_responsive_control(
			'card_radius',
			[
				'label'      => esc_html__( 'Border Radius', 'chm-rss-display' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'default'    => [ 'top' => 6, 'right' => 6, 'bottom' => 6, 'left' => 6, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .chm-rss-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->start_controls_tabs( 'card_shadow_tabs' );

		$this->start_controls_tab( 'card_shadow_normal', [ 'label' => esc_html__( 'Normal', 'chm-rss-display' ) ] );
		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name'     => 'card_shadow',
				'selector' => '{{WRAPPER}} .chm-rss-card',
			]
		);
		$this->end_controls_tab();

		$this->start_controls_tab( 'card_shadow_hover', [ 'label' => esc_html__( 'Hover', 'chm-rss-display' ) ] );
		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name'     => 'card_shadow_hover',
				'selector' => '{{WRAPPER}} .chm-rss-card:hover',
			]
		);
		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'hover_effect',
			[
				'label'     => esc_html__( 'Hover Effect', 'chm-rss-display' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'lift',
				'separator' => 'before',
				'options'   => [
					'none' => esc_html__( 'None', 'chm-rss-display' ),
					'lift' => esc_html__( 'Lift', 'chm-rss-display' ),
				],
				'prefix_class' => 'chm-rss-hover-',
			]
		);

		$this->add_responsive_control(
			'card_padding',
			[
				'label'      => esc_html__( 'Content Padding', 'chm-rss-display' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em' ],
				'default'    => [ 'top' => 14, 'right' => 16, 'bottom' => 16, 'left' => 16, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .chm-rss-card__body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'content_align',
			[
				'label'     => esc_html__( 'Content Alignment', 'chm-rss-display' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => [
					'left'   => [ 'title' => esc_html__( 'Left', 'chm-rss-display' ), 'icon' => 'eicon-text-align-left' ],
					'center' => [ 'title' => esc_html__( 'Center', 'chm-rss-display' ), 'icon' => 'eicon-text-align-center' ],
					'right'  => [ 'title' => esc_html__( 'Right', 'chm-rss-display' ), 'icon' => 'eicon-text-align-right' ],
				],
				'selectors' => [
					'{{WRAPPER}} .chm-rss-card__body' => 'text-align: {{VALUE}};',
				],
			]
		);

		$this->end_controls_section();
	}

	private function register_style_image(): void {
		$this->start_controls_section(
			'section_style_image',
			[
				'label'     => esc_html__( 'Image', 'chm-rss-display' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'show_image' => 'yes' ],
			]
		);

		$this->add_control(
			'image_ratio',
			[
				'label'   => esc_html__( 'Aspect Ratio', 'chm-rss-display' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '2/3',
				'options' => [
					'2/3'  => esc_html__( '2:3 (Book)', 'chm-rss-display' ),
					'1/1'  => esc_html__( '1:1', 'chm-rss-display' ),
					'16/9' => esc_html__( '16:9', 'chm-rss-display' ),
					'auto' => esc_html__( 'Auto', 'chm-rss-display' ),
				],
				'selectors' => [
					'{{WRAPPER}} .chm-rss-card__media' => 'aspect-ratio: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'image_fit',
			[
				'label'       => esc_html__( 'Image Fit', 'chm-rss-display' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'contain',
				'options'     => [
					'contain' => esc_html__( 'Fit — show the full cover', 'chm-rss-display' ),
					'cover'   => esc_html__( 'Fill — crop to the frame', 'chm-rss-display' ),
				],
				'description' => esc_html__( 'Fit shows odd-shaped art (CDs, audio) over a blurred backdrop; Fill crops it to the frame.', 'chm-rss-display' ),
				'selectors'   => [
					'{{WRAPPER}} .chm-rss-card__media .chm-rss-card__img' => 'object-fit: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'image_radius',
			[
				'label'      => esc_html__( 'Border Radius', 'chm-rss-display' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'selectors'  => [
					'{{WRAPPER}} .chm-rss-card__media' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
				],
			]
		);

		$this->add_responsive_control(
			'list_image_width',
			[
				'label'      => esc_html__( 'Image Width (List)', 'chm-rss-display' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [ 'px' => [ 'min' => 48, 'max' => 240 ] ],
				'default'    => [ 'size' => 100 ],
				'mobile_default' => [ 'size' => 72 ],
				'condition'  => [ 'view' => 'list' ],
				'selectors'  => [
					'{{WRAPPER}} .chm-rss-card--row .chm-rss-card__media' => 'flex: 0 0 {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	private function register_style_text(): void {

		// Category / format label.
		$this->start_controls_section(
			'section_style_category',
			[
				'label'     => esc_html__( 'Category Label', 'chm-rss-display' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'show_category' => 'yes' ],
			]
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[ 'name' => 'category_typography', 'selector' => '{{WRAPPER}} .chm-rss-card__format' ]
		);
		$this->add_control(
			'category_color',
			[
				'label'     => esc_html__( 'Color', 'chm-rss-display' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#8A8264',
				'selectors' => [ '{{WRAPPER}} .chm-rss-card__format' => 'color: {{VALUE}};' ],
			]
		);
		$this->end_controls_section();

		// Title.
		$this->start_controls_section(
			'section_style_title',
			[ 'label' => esc_html__( 'Title', 'chm-rss-display' ), 'tab' => Controls_Manager::TAB_STYLE ]
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[ 'name' => 'title_typography', 'selector' => '{{WRAPPER}} .chm-rss-card__title' ]
		);
		$this->add_control(
			'title_color',
			[
				'label'     => esc_html__( 'Color', 'chm-rss-display' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#1B1B1B',
				'selectors' => [ '{{WRAPPER}} .chm-rss-card__title' => 'color: {{VALUE}};' ],
			]
		);
		$this->add_control(
			'title_hover_color',
			[
				'label'     => esc_html__( 'Hover Color', 'chm-rss-display' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#1F4E9C',
				'selectors' => [ '{{WRAPPER}} .chm-rss-card:hover .chm-rss-card__title' => 'color: {{VALUE}};' ],
			]
		);
		$this->add_responsive_control(
			'title_spacing',
			[
				'label'      => esc_html__( 'Spacing', 'chm-rss-display' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
				'selectors'  => [ '{{WRAPPER}} .chm-rss-card__title' => 'margin-bottom: {{SIZE}}{{UNIT}};' ],
			]
		);
		$this->end_controls_section();

		// Author.
		$this->start_controls_section(
			'section_style_author',
			[
				'label'     => esc_html__( 'Author', 'chm-rss-display' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'show_author' => 'yes' ],
			]
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[ 'name' => 'author_typography', 'selector' => '{{WRAPPER}} .chm-rss-card__author' ]
		);
		$this->add_control(
			'author_color',
			[
				'label'     => esc_html__( 'Color', 'chm-rss-display' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#545454',
				'selectors' => [ '{{WRAPPER}} .chm-rss-card__author' => 'color: {{VALUE}};' ],
			]
		);
		$this->end_controls_section();

		// Description.
		$this->start_controls_section(
			'section_style_desc',
			[
				'label'     => esc_html__( 'Description', 'chm-rss-display' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'show_description' => 'yes' ],
			]
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[ 'name' => 'desc_typography', 'selector' => '{{WRAPPER}} .chm-rss-card__desc' ]
		);
		$this->add_control(
			'desc_color',
			[
				'label'     => esc_html__( 'Color', 'chm-rss-display' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#545454',
				'selectors' => [ '{{WRAPPER}} .chm-rss-card__desc' => 'color: {{VALUE}};' ],
			]
		);
		$this->end_controls_section();

		// Date.
		$this->start_controls_section(
			'section_style_date',
			[
				'label'     => esc_html__( 'Date', 'chm-rss-display' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'show_date' => 'yes' ],
			]
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[ 'name' => 'date_typography', 'selector' => '{{WRAPPER}} .chm-rss-card__date' ]
		);
		$this->add_control(
			'date_color',
			[
				'label'     => esc_html__( 'Color', 'chm-rss-display' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#8A8264',
				'selectors' => [ '{{WRAPPER}} .chm-rss-card__date' => 'color: {{VALUE}};' ],
			]
		);
		$this->end_controls_section();

		// Section heading.
		$this->start_controls_section(
			'section_style_heading',
			[ 'label' => esc_html__( 'Section Heading', 'chm-rss-display' ), 'tab' => Controls_Manager::TAB_STYLE ]
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[ 'name' => 'heading_typography', 'selector' => '{{WRAPPER}} .chm-rss__heading' ]
		);
		$this->add_control(
			'heading_color',
			[
				'label'     => esc_html__( 'Color', 'chm-rss-display' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#1B1B1B',
				'selectors' => [ '{{WRAPPER}} .chm-rss__heading' => 'color: {{VALUE}};' ],
			]
		);
		$this->add_responsive_control(
			'heading_spacing',
			[
				'label'      => esc_html__( 'Spacing', 'chm-rss-display' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 80 ] ],
				'default'    => [ 'size' => 28 ],
				'selectors'  => [ '{{WRAPPER}} .chm-rss__heading' => 'margin-bottom: {{SIZE}}{{UNIT}};' ],
			]
		);
		$this->end_controls_section();
	}

	private function register_style_cta(): void {
		$this->start_controls_section(
			'section_style_cta',
			[
				'label'     => esc_html__( 'Read More', 'chm-rss-display' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'show_cta' => 'yes' ],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[ 'name' => 'cta_typography', 'selector' => '{{WRAPPER}} .chm-rss-card__cta' ]
		);

		$this->start_controls_tabs( 'cta_color_tabs' );

		$this->start_controls_tab( 'cta_normal', [ 'label' => esc_html__( 'Normal', 'chm-rss-display' ) ] );
		$this->add_control(
			'cta_text_color',
			[
				'label'     => esc_html__( 'Text Color', 'chm-rss-display' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}} .chm-rss-card__cta' => 'color: {{VALUE}};' ],
			]
		);
		$this->add_control(
			'cta_bg_color',
			[
				'label'     => esc_html__( 'Background Color', 'chm-rss-display' ),
				'type'      => Controls_Manager::COLOR,
				'condition' => [ 'cta_style' => 'button' ],
				'selectors' => [ '{{WRAPPER}} .chm-rss-card__cta--button' => 'background-color: {{VALUE}};' ],
			]
		);
		$this->end_controls_tab();

		$this->start_controls_tab( 'cta_hover', [ 'label' => esc_html__( 'Hover', 'chm-rss-display' ) ] );
		$this->add_control(
			'cta_text_color_hover',
			[
				'label'     => esc_html__( 'Text Color', 'chm-rss-display' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}} .chm-rss-card:hover .chm-rss-card__cta' => 'color: {{VALUE}};' ],
			]
		);
		$this->add_control(
			'cta_bg_color_hover',
			[
				'label'     => esc_html__( 'Background Color', 'chm-rss-display' ),
				'type'      => Controls_Manager::COLOR,
				'condition' => [ 'cta_style' => 'button' ],
				'selectors' => [ '{{WRAPPER}} .chm-rss-card:hover .chm-rss-card__cta--button' => 'background-color: {{VALUE}};' ],
			]
		);
		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_responsive_control(
			'cta_padding',
			[
				'label'      => esc_html__( 'Padding', 'chm-rss-display' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em' ],
				'separator'  => 'before',
				'condition'  => [ 'cta_style' => 'button' ],
				'selectors'  => [
					'{{WRAPPER}} .chm-rss-card__cta--button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'cta_radius',
			[
				'label'      => esc_html__( 'Border Radius', 'chm-rss-display' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'condition'  => [ 'cta_style' => 'button' ],
				'selectors'  => [
					'{{WRAPPER}} .chm-rss-card__cta--button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	private function register_style_nav(): void {
		$this->start_controls_section(
			'section_style_nav',
			[
				'label'     => esc_html__( 'Carousel Navigation', 'chm-rss-display' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'view' => 'carousel' ],
			]
		);

		$this->add_control(
			'arrows_heading',
			[ 'label' => esc_html__( 'Arrows', 'chm-rss-display' ), 'type' => Controls_Manager::HEADING ]
		);

		$this->add_responsive_control(
			'arrow_size',
			[
				'label'      => esc_html__( 'Size', 'chm-rss-display' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [ 'px' => [ 'min' => 24, 'max' => 72 ] ],
				'default'    => [ 'size' => 40 ],
				'mobile_default' => [ 'size' => 36 ],
				'condition'  => [ 'show_arrows' => 'yes' ],
				'selectors'  => [
					'{{WRAPPER}} .chm-rss__arrow' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'arrow_color',
			[
				'label'     => esc_html__( 'Icon Color', 'chm-rss-display' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#1F4E9C',
				'condition' => [ 'show_arrows' => 'yes' ],
				'selectors' => [ '{{WRAPPER}} .chm-rss__arrow' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'arrow_bg',
			[
				'label'     => esc_html__( 'Background', 'chm-rss-display' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#FFFFFF',
				'condition' => [ 'show_arrows' => 'yes' ],
				'selectors' => [ '{{WRAPPER}} .chm-rss__arrow' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'dots_heading',
			[ 'label' => esc_html__( 'Dots', 'chm-rss-display' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ]
		);

		$this->add_control(
			'dot_color',
			[
				'label'     => esc_html__( 'Color', 'chm-rss-display' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#D9D0B0',
				'condition' => [ 'show_dots' => 'yes' ],
				'selectors' => [ '{{WRAPPER}} .chm-rss .swiper-pagination-bullet' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'dot_active_color',
			[
				'label'     => esc_html__( 'Active Color', 'chm-rss-display' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#1F4E9C',
				'condition' => [ 'show_dots' => 'yes' ],
				'selectors' => [ '{{WRAPPER}} .chm-rss .swiper-pagination-bullet-active' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->add_responsive_control(
			'dot_size',
			[
				'label'      => esc_html__( 'Dot Size', 'chm-rss-display' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [ 'px' => [ 'min' => 4, 'max' => 20 ] ],
				'default'    => [ 'size' => 9 ],
				'condition'  => [ 'show_dots' => 'yes' ],
				'selectors'  => [
					'{{WRAPPER}} .chm-rss .swiper-pagination-bullet' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/* ---------------------------------------------------------------------
	 * Render
	 * ------------------------------------------------------------------- */

	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$feed_url = isset( $settings['feed_url']['url'] ) ? $settings['feed_url']['url'] : '';
		$editor   = \Elementor\Plugin::$instance->editor->is_edit_mode();

		if ( '' === trim( (string) $feed_url ) ) {
			if ( $editor ) {
				$this->render_placeholder( esc_html__( 'Add a feed URL in the Feed section to display items.', 'chm-rss-display' ) );
			}
			return;
		}

		$fetcher = new Feed_Fetcher();
		$items   = $fetcher->get_items( $feed_url, (int) $settings['cache_ttl'] );

		if ( empty( $items ) ) {
			if ( $editor ) {
				$this->render_placeholder( esc_html__( 'Feed unavailable or empty — check the URL.', 'chm-rss-display' ) );
			}
			return;
		}

		$count = ! empty( $settings['items_count']['size'] ) ? (int) $settings['items_count']['size'] : 10;
		$items = array_slice( $items, 0, max( 1, $count ) );
		$view  = in_array( $settings['view'], [ 'carousel', 'grid', 'list' ], true ) ? $settings['view'] : 'carousel';

		$this->add_render_attribute( 'wrapper', 'class', [ 'chm-rss', 'chm-rss--' . $view ] );

		echo '<div ' . $this->get_render_attribute_string( 'wrapper' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput

		$heading = trim( (string) $settings['heading_text'] );
		if ( '' !== $heading ) {
			echo '<h2 class="chm-rss__heading">' . esc_html( $heading ) . '</h2>';
		}

		switch ( $view ) {
			case 'grid':
				$this->render_grid( $items, $settings );
				break;
			case 'list':
				$this->render_list( $items, $settings );
				break;
			default:
				$this->render_carousel( $items, $settings );
		}

		echo '</div>';
	}

	/**
	 * @param \ChmRss\Feed_Item[] $items    Feed items.
	 * @param array                $settings Widget settings.
	 */
	private function render_grid( array $items, array $settings ): void {
		echo '<div class="chm-rss__grid">';
		foreach ( $items as $item ) {
			$this->render_card( $item, $settings, 'card' );
		}
		echo '</div>';
	}

	/**
	 * @param \ChmRss\Feed_Item[] $items    Feed items.
	 * @param array                $settings Widget settings.
	 */
	private function render_list( array $items, array $settings ): void {
		echo '<div class="chm-rss__list">';
		foreach ( $items as $item ) {
			$this->render_card( $item, $settings, 'row' );
		}
		echo '</div>';
	}

	/**
	 * @param \ChmRss\Feed_Item[] $items    Feed items.
	 * @param array                $settings Widget settings.
	 */
	private function render_carousel( array $items, array $settings ): void {
		$container_class = $this->get_swiper_container_class();

		$config = [
			'slidesToShow'       => $this->responsive_size( $settings, 'slides_to_show', 4 ),
			'slidesToShowTablet' => $this->responsive_size( $settings, 'slides_to_show_tablet', 2 ),
			'slidesToShowMobile' => $this->responsive_size( $settings, 'slides_to_show_mobile', 1 ),
			'slidesToScroll'     => $this->responsive_size( $settings, 'slides_to_scroll', 1 ),
			'spaceBetween'       => $this->responsive_size( $settings, 'space_between', 24 ),
			'spaceBetweenTablet' => $this->responsive_size( $settings, 'space_between_tablet', 24 ),
			'spaceBetweenMobile' => $this->responsive_size( $settings, 'space_between_mobile', 16 ),
			'autoplay'           => 'yes' === $settings['autoplay'],
			'autoplayDelay'      => (int) $settings['autoplay_delay'],
			'pauseOnHover'       => 'yes' === $settings['pause_on_hover'],
			'loop'               => 'yes' === $settings['infinite_loop'],
			'speed'              => (int) $settings['transition_speed'],
			'arrows'             => 'yes' === $settings['show_arrows'],
			'dots'               => 'yes' === $settings['show_dots'],
		];

		$this->add_render_attribute(
			'carousel',
			[
				'class'         => [ 'chm-rss__carousel', $container_class ],
				'data-settings' => wp_json_encode( $config ),
			]
		);

		echo '<div class="chm-rss__carousel-wrap">';
		echo '<div ' . $this->get_render_attribute_string( 'carousel' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="swiper-wrapper">';

		foreach ( $items as $item ) {
			echo '<div class="swiper-slide">';
			$this->render_card( $item, $settings, 'card' );
			echo '</div>';
		}

		echo '</div>'; // .swiper-wrapper

		if ( $config['dots'] ) {
			echo '<div class="swiper-pagination chm-rss__dots"></div>';
		}

		echo '</div>'; // container

		if ( $config['arrows'] ) {
			printf(
				'<button type="button" class="chm-rss__arrow chm-rss__arrow--prev" aria-label="%s"><span aria-hidden="true">&lsaquo;</span></button>',
				esc_attr__( 'Previous items', 'chm-rss-display' )
			);
			printf(
				'<button type="button" class="chm-rss__arrow chm-rss__arrow--next" aria-label="%s"><span aria-hidden="true">&rsaquo;</span></button>',
				esc_attr__( 'Next items', 'chm-rss-display' )
			);
		}

		echo '</div>'; // .chm-rss__carousel-wrap
	}

	/**
	 * Render one card. The whole card is a single anchor, per the approved design.
	 *
	 * @param \ChmRss\Feed_Item $item     Feed item.
	 * @param array              $settings Widget settings.
	 * @param string             $context  'card' or 'row'.
	 */
	private function render_card( $item, array $settings, string $context ): void {
		$new_tab   = 'yes' === $settings['new_tab'];
		$title_tag = in_array( $settings['title_tag'], [ 'h2', 'h3', 'h4', 'h5', 'h6', 'div' ], true ) ? $settings['title_tag'] : 'h3';

		$classes = [ 'chm-rss-card' ];
		if ( 'row' === $context ) {
			$classes[] = 'chm-rss-card--row';
		}

		printf(
			'<a class="%1$s" href="%2$s"%3$s>',
			esc_attr( implode( ' ', $classes ) ),
			esc_url( $item->link ),
			$new_tab ? ' target="_blank" rel="noopener noreferrer"' : ''
		);

		// Media.
		if ( 'yes' === $settings['show_image'] ) {
			echo '<div class="chm-rss-card__media">';
			if ( $item->has_image() ) {
				printf(
					'<span class="chm-rss-card__backdrop" style="background-image:url(%1$s)" aria-hidden="true"></span><img class="chm-rss-card__img" src="%1$s" alt="%2$s" loading="lazy" decoding="async">',
					esc_url( $item->image ),
					esc_attr( $item->title )
				);
			} else {
				echo '<div class="chm-rss-card__placeholder">';
				echo '<span class="chm-rss-card__placeholder-book" aria-hidden="true"></span>';
				echo '<span class="chm-rss-card__placeholder-title">' . esc_html( $item->title ) . '</span>';
				echo '<span class="chm-rss-card__placeholder-note">' . esc_html__( 'cover unavailable', 'chm-rss-display' ) . '</span>';
				echo '</div>';
			}
			echo '</div>';
		}

		echo '<div class="chm-rss-card__body">';

		if ( 'yes' === $settings['show_category'] && '' !== $item->category ) {
			echo '<div class="chm-rss-card__format">' . esc_html( $item->category ) . '</div>';
		}

		printf(
			'<%1$s class="chm-rss-card__title">%2$s</%1$s>',
			esc_html( $title_tag ), // phpcs:ignore WordPress.Security.EscapeOutput -- whitelisted tag.
			esc_html( $item->title )
		);

		if ( 'yes' === $settings['show_author'] && '' !== $item->author ) {
			echo '<div class="chm-rss-card__author">' . esc_html( $item->author ) . '</div>';
		}

		if ( 'yes' === $settings['show_description'] && '' !== $item->description ) {
			$length = ! empty( $settings['desc_length'] ) ? (int) $settings['desc_length'] : 25;
			echo '<div class="chm-rss-card__desc">' . esc_html( wp_trim_words( $item->description, $length, '…' ) ) . '</div>';
		}

		if ( 'yes' === $settings['show_date'] && null !== $item->timestamp ) {
			echo '<div class="chm-rss-card__date">' . esc_html( date_i18n( get_option( 'date_format' ), $item->timestamp ) ) . '</div>';
		}

		if ( 'yes' === $settings['show_cta'] ) {
			$cta_class = 'button' === $settings['cta_style'] ? 'chm-rss-card__cta--button' : 'chm-rss-card__cta--link';
			echo '<div class="chm-rss-card__foot">';
			printf(
				'<span class="chm-rss-card__cta %1$s">%2$s <span class="chm-rss-card__cta-icon" aria-hidden="true">&#8599;</span>%3$s</span>',
				esc_attr( $cta_class ),
				esc_html( $settings['cta_text'] ),
				$new_tab ? '<span class="screen-reader-text"> ' . esc_html__( '(opens in new tab)', 'chm-rss-display' ) . '</span>' : ''
			);
			echo '</div>';
		}

		echo '</div>'; // __body
		echo '</a>';
	}

	/**
	 * Editor-only placeholder box.
	 *
	 * @param string $message Message to show.
	 */
	private function render_placeholder( string $message ): void {
		echo '<div class="chm-rss__placeholder">' . esc_html( $message ) . '</div>';
	}

	/**
	 * Pull a size value out of an Elementor slider setting with a fallback.
	 *
	 * @param array  $settings Widget settings.
	 * @param string $key      Setting key.
	 * @param int    $fallback Fallback value.
	 * @return int
	 */
	private function responsive_size( array $settings, string $key, int $fallback ): int {
		if ( isset( $settings[ $key ]['size'] ) && '' !== $settings[ $key ]['size'] && null !== $settings[ $key ]['size'] ) {
			return max( 1, (int) $settings[ $key ]['size'] );
		}
		return $fallback;
	}

	/**
	 * Elementor ships Swiper under 'swiper' or legacy 'swiper-container'
	 * depending on the e_swiper_latest experiment. Default to modern.
	 *
	 * @return string
	 */
	private function get_swiper_container_class(): string {
		$experiments = \Elementor\Plugin::$instance->experiments;

		if ( $experiments && method_exists( $experiments, 'get_features' ) ) {
			$feature = $experiments->get_features( 'e_swiper_latest' );
			if ( null !== $feature && ! $experiments->is_feature_active( 'e_swiper_latest' ) ) {
				return 'swiper-container';
			}
		}

		return 'swiper';
	}
}
