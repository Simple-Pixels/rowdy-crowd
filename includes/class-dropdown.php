<?php
/**
 * Shared dropdown component.
 *
 * @package RowdyCrowd
 */

namespace RowdyCrowd\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the dropdown behind [region_switcher] and [currency_switcher], and loads its assets.
 *
 * Markup, styles and behaviour live here so every switcher looks and works the same.
 */
final class Dropdown {

	private const HANDLE = 'rowdy-crowd-dropdown';

	private static bool $registered = false;

	/**
	 * Hooks the shared assets into WordPress. Every module that renders a dropdown calls this.
	 */
	public static function register(): void {
		if ( self::$registered ) {
			return;
		}

		self::$registered = true;
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
	}

	/**
	 * Loads the assets on every page, because switchers normally sit in the header or footer.
	 */
	public static function enqueue_assets(): void {
		wp_enqueue_style( self::HANDLE, ROWDY_CROWD_URL . 'assets/dropdown.css', array(), ROWDY_CROWD_VERSION );
		wp_enqueue_script(
			self::HANDLE,
			ROWDY_CROWD_URL . 'assets/dropdown.js',
			array(),
			ROWDY_CROWD_VERSION,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
	}

	/**
	 * Returns the dropdown markup.
	 *
	 * @param string                                                                                                   $modifier_class     Extra class for the wrapper, e.g. 'rowdy-crowd-region-switcher'.
	 * @param string                                                                                                   $toggle_text        Visible text on the toggle button.
	 * @param string                                                                                                   $toggle_description Accessible name for the toggle button.
	 * @param array<int, array{label: string, url: string, current?: bool, attrs?: array<string, string>}> $options Menu options. Each is a link; 'attrs' adds extra attributes.
	 */
	public static function render( string $modifier_class, string $toggle_text, string $toggle_description, array $options ): string {
		$menu_id = wp_unique_id( self::HANDLE . '-' );
		$items   = '';

		foreach ( $options as $option ) {
			$attrs = '';

			foreach ( $option['attrs'] ?? array() as $name => $value ) {
				$attrs .= sprintf( ' %s="%s"', esc_attr( $name ), esc_attr( $value ) );
			}

			$items .= sprintf(
				'<li><a class="rowdy-crowd-dropdown__option" href="%1$s" rel="nofollow"%2$s%3$s>%4$s</a></li>',
				esc_url( $option['url'] ),
				empty( $option['current'] ) ? '' : ' aria-current="true"',
				$attrs,
				esc_html( $option['label'] )
			);
		}

		return sprintf(
			'<div class="rowdy-crowd-dropdown %1$s" data-rowdy-crowd-dropdown>'
				. '<button type="button" class="rowdy-crowd-dropdown__toggle" aria-expanded="false" aria-controls="%2$s" aria-label="%3$s">'
				. '<span class="rowdy-crowd-dropdown__label">%4$s</span>'
				. '<svg class="rowdy-crowd-dropdown__chevron" viewBox="0 0 12 8" width="12" height="8" aria-hidden="true" focusable="false"><path d="M1 1.5l5 5 5-5" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>'
				. '</button>'
				. '<ul id="%2$s" class="rowdy-crowd-dropdown__menu" hidden>%5$s</ul>'
				. '</div>',
			esc_attr( $modifier_class ),
			esc_attr( $menu_id ),
			esc_attr( $toggle_description ),
			esc_html( $toggle_text ),
			$items
		);
	}
}
