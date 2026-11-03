<?php
/**
 * Store finder module.
 *
 * @package RowdyCrowd
 */

namespace RowdyCrowd\Modules\Store_Finder;

use RowdyCrowd\Core\Module as Base_Module;

defined( 'ABSPATH' ) || exit;

/**
 * A store locator for Rowdy Crowd's stockists: a Stockists post type, its fields, a settings
 * page for Google Maps, and the [store_finder] shortcode.
 */
final class Module extends Base_Module {

	public function get_id(): string {
		return 'store-finder';
	}

	public function register(): void {
		( new Stockist_Post_Type() )->register();
		( new Stockist_Fields() )->register();
		( new Settings() )->register();
		( new Shortcode( $this->url( 'assets/' ), $this->path( 'templates/store-finder.php' ) ) )->register();
	}
}
