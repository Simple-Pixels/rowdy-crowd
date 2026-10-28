<?php
/**
 * Region switcher shortcode.
 *
 * @package RowdyCrowd
 */

namespace RowdyCrowd\Modules\Geolocation;

defined( 'ABSPATH' ) || exit;

/**
 * [region_switcher] — a flag dropdown that moves the visitor between regions.
 *
 * Attributes:
 * - show_label: "yes" (default) or "no". When "no", only the flag shows on the toggle.
 */
final class Switcher {

	public const SHORTCODE = 'region_switcher';

	private const HANDLE = 'rowdy-crowd-region-switcher';

	/**
	 * @param string $assets_url URL of the module's assets folder (trailing slash).
	 */
	public function __construct( private string $assets_url ) {}

	public function register(): void {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueued on every page: the switcher normally sits in the site header.
	 */
	public function enqueue_assets(): void {
		wp_enqueue_style( self::HANDLE, $this->assets_url . 'switcher.css', array(), ROWDY_CROWD_VERSION );
		wp_enqueue_script(
			self::HANDLE,
			$this->assets_url . 'switcher.js',
			array(),
			ROWDY_CROWD_VERSION,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
	}

	/**
	 * Shortcode output.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts( array( 'show_label' => 'yes' ), $atts, self::SHORTCODE );

		$current    = Region::current();
		$show_label = 'no' !== $atts['show_label'];
		$menu_id    = wp_unique_id( self::HANDLE . '-' );
		$label_html = $show_label
			? '<span class="rowdy-crowd-region-switcher__label">' . esc_html( Region::label( $current ) ) . '</span>'
			: '';

		$options = '';

		foreach ( Region::all() as $region => $label ) {
			$options .= sprintf(
				'<li><a class="rowdy-crowd-region-switcher__option" href="%s" hreflang="%s"%s>%s<span>%s</span></a></li>',
				esc_url( Region::url_for( $region ) ),
				esc_attr( Region::hreflang( $region ) ),
				$region === $current ? ' aria-current="true"' : '',
				$this->flag( $region ),
				esc_html( $label )
			);
		}

		return sprintf(
			'<div class="rowdy-crowd-region-switcher" data-rowdy-crowd-region-switcher>'
				. '<button type="button" class="rowdy-crowd-region-switcher__toggle" aria-expanded="false" aria-controls="%1$s" aria-label="%2$s">'
				. '%3$s%4$s'
				. '<svg class="rowdy-crowd-region-switcher__chevron" viewBox="0 0 12 8" width="12" height="8" aria-hidden="true" focusable="false"><path d="M1 1.5l5 5 5-5" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>'
				. '</button>'
				. '<ul id="%1$s" class="rowdy-crowd-region-switcher__menu" hidden>%5$s</ul>'
				. '</div>',
			esc_attr( $menu_id ),
			/* translators: %s: current region name. */
			esc_attr( sprintf( __( 'Region: %s. Change region', 'rowdy-crowd' ), Region::label( $current ) ) ),
			$this->flag( $current ),
			$label_html,
			$options
		);
	}

	/**
	 * Decorative flag image for a region. The region name is always shown or read alongside it.
	 *
	 * @param string $region Region key.
	 */
	private function flag( string $region ): string {
		return sprintf(
			'<img class="rowdy-crowd-region-switcher__flag" src="%s" alt="" width="24" height="16" />',
			esc_url( $this->assets_url . 'flags/' . $region . '.svg' )
		);
	}
}
