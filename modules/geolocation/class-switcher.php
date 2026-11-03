<?php
/**
 * Region switcher shortcode.
 *
 * @package RowdyCrowd
 */

namespace RowdyCrowd\Modules\Geolocation;

use RowdyCrowd\Core\Dropdown;

defined( 'ABSPATH' ) || exit;

/**
 * [region_switcher] — a dropdown that moves the visitor between regions.
 *
 * Each option links to the same page on the region's domain. The link also carries the
 * region's currency (see Region::currencies()), so switching region switches currency too.
 */
final class Switcher {

	public const SHORTCODE = 'region_switcher';

	public function register(): void {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
		Dropdown::register();
	}

	public function render(): string {
		$current = Region::current();
		$options = array();

		foreach ( Region::all() as $region => $label ) {
			$options[] = array(
				'label'   => $label,
				'url'     => Region::url_for( $region ),
				'current' => $region === $current,
				'attrs'   => array( 'hreflang' => Region::hreflang( $region ) ),
			);
		}

		return Dropdown::render(
			'rowdy-crowd-region-switcher',
			Region::label( $current ),
			/* translators: %s: current region name. */
			sprintf( __( 'Region: %s. Change region', 'rowdy-crowd' ), Region::label( $current ) ),
			$options
		);
	}
}
