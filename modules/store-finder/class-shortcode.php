<?php
/**
 * Store finder shortcode.
 *
 * @package RowdyCrowd
 */

namespace RowdyCrowd\Modules\Store_Finder;

use RowdyCrowd\Modules\Geolocation\Region;

defined( 'ABSPATH' ) || exit;

/**
 * [store_finder] — a searchable list of stores next to a Google Map.
 *
 * Attributes:
 * - region: "current" (default, the visitor's site), "aunz", "usa" or "all".
 * - height: map and list height as a CSS length, e.g. "560px" (default).
 * - class:  extra CSS classes for the wrapper.
 *
 * The list is rendered on the server, so it works without JavaScript and is cached with
 * the page. The script only adds search, the state filter and the map.
 */
final class Shortcode {

	public const TAG = 'store_finder';

	private const SCRIPT_HANDLE  = 'rowdy-crowd-store-finder';
	private const MAPS_HANDLE    = 'rowdy-crowd-google-maps';
	private const DEFAULT_HEIGHT = '560px';

	/**
	 * Most states that get a filter chip each. More than this (e.g. US states) uses a dropdown.
	 */
	private const MAX_CHIP_STATES = 12;

	/**
	 * @param string $assets_url    URL of the module's assets folder (trailing slash).
	 * @param string $template_path Absolute path of the markup template.
	 */
	public function __construct( private string $assets_url, private string $template_path ) {}

	public function register(): void {
		add_shortcode( self::TAG, array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Registers the assets. They're only enqueued on pages that use the shortcode.
	 */
	public function register_assets(): void {
		wp_register_style( self::SCRIPT_HANDLE, $this->assets_url . 'store-finder.css', array(), ROWDY_CROWD_VERSION );
		wp_register_script( self::SCRIPT_HANDLE, $this->assets_url . 'store-finder.js', array(), ROWDY_CROWD_VERSION, true );

		$api_key = Settings::api_key();

		if ( '' === $api_key ) {
			return;
		}

		// Depends on our script so that its init callback exists before Google calls it.
		wp_register_script(
			self::MAPS_HANDLE,
			add_query_arg(
				array(
					'key'      => $api_key,
					'v'        => 'weekly',
					'loading'  => 'async',
					'callback' => 'rowdyCrowdStoreFinderInit',
				),
				'https://maps.googleapis.com/maps/api/js'
			),
			array( self::SCRIPT_HANDLE ),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google rejects extra query args.
			true
		);
	}

	/**
	 * @param array<string, string>|string $atts Shortcode attributes.
	 */
	public function render( $atts ): string {
		if ( ! function_exists( 'get_field' ) ) {
			return $this->admin_notice( __( 'The store finder needs the Advanced Custom Fields plugin.', 'rowdy-crowd' ) );
		}

		$atts = shortcode_atts(
			array(
				'region' => 'current',
				'height' => self::DEFAULT_HEIGHT,
				'class'  => '',
			),
			$atts,
			self::TAG
		);

		$region    = $this->resolve_region( (string) $atts['region'] );
		$stockists = Stockists::for_region( $region );

		if ( empty( $stockists ) ) {
			return '<p class="rowdy-crowd-store-finder-empty">' . esc_html__( 'We don\'t have any stores listed here yet. Please check back soon.', 'rowdy-crowd' ) . '</p>';
		}

		$has_map = '' !== Settings::api_key();

		wp_enqueue_style( self::SCRIPT_HANDLE );
		wp_enqueue_script( self::SCRIPT_HANDLE );

		if ( $has_map ) {
			wp_enqueue_script( self::MAPS_HANDLE );
		}

		$states = Stockists::states( $stockists );

		return $this->render_template(
			array(
				'id'         => wp_unique_id( 'rowdy-crowd-store-finder-' ),
				'classes'    => trim( 'rowdy-crowd-store-finder ' . ( $has_map ? 'rowdy-crowd-store-finder--map ' : '' ) . $this->sanitize_classes( (string) $atts['class'] ) ),
				'height'     => $this->sanitize_height( (string) $atts['height'] ),
				'pin_color'  => Settings::pin_color(),
				'map_id'     => Settings::map_id(),
				'has_map'    => $has_map,
				'stockists'  => $stockists,
				'states'     => $states,
				'counts'     => Stockists::state_counts( $stockists ),
				'use_chips'  => count( $states ) > 1 && count( $states ) <= self::MAX_CHIP_STATES,
				// Distances in miles for the US site, kilometres everywhere else.
				'units'      => Region::USA === $region ? 'mi' : 'km',
				'notice'     => $has_map ? '' : $this->admin_notice( __( 'The map is hidden until a Google Maps API key is added under Stockists → Settings.', 'rowdy-crowd' ) ),
			)
		);
	}

	/**
	 * Region keys the shortcode accepts, as the stockist query wants them ('' means every region).
	 *
	 * @param string $value Shortcode attribute.
	 */
	private function resolve_region( string $value ): string {
		$value = strtolower( trim( $value ) );

		if ( 'all' === $value ) {
			return '';
		}

		return Region::is_valid( $value ) ? $value : Region::current();
	}

	/**
	 * @param string $height Any CSS length the finder can use.
	 */
	private function sanitize_height( string $height ): string {
		$height = trim( $height );

		return preg_match( '/^\d+(\.\d+)?(px|vh|rem|em|%)$/', $height ) ? $height : self::DEFAULT_HEIGHT;
	}

	/**
	 * @param string $classes Space-separated CSS classes.
	 */
	private function sanitize_classes( string $classes ): string {
		return implode( ' ', array_filter( array_map( 'sanitize_html_class', explode( ' ', $classes ) ) ) );
	}

	/**
	 * A message only administrators see, so visitors never get setup instructions.
	 *
	 * @param string $message Message.
	 */
	private function admin_notice( string $message ): string {
		return current_user_can( 'manage_options' ) ? '<p class="rowdy-crowd-store-finder__notice">' . esc_html( $message ) . '</p>' : '';
	}

	/**
	 * @param array<string, mixed> $args Values for the template (available there as $args).
	 */
	private function render_template( array $args ): string {
		ob_start();
		include $this->template_path;

		return (string) ob_get_clean();
	}
}
