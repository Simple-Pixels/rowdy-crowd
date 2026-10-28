<?php
/**
 * Base class for feature modules.
 *
 * @package RowdyCrowd
 */

namespace RowdyCrowd\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Every feature (quoting, geolocation, product renderer, ...) extends this class
 * and lives in its own folder under /modules.
 */
abstract class Module {

	/**
	 * Unique module ID, e.g. 'hello-world'. Matches the module's folder name.
	 */
	abstract public function get_id(): string;

	/**
	 * Hooks the module into WordPress. Called once during plugin boot.
	 */
	abstract public function register(): void;

	/**
	 * Whether the module should load. Override to gate on dependencies
	 * (e.g. WooCommerce being active).
	 */
	public function is_enabled(): bool {
		return true;
	}

	/**
	 * Absolute path to a file inside this module's folder.
	 *
	 * @param string $relative_path Path relative to the module folder.
	 */
	protected function path( string $relative_path = '' ): string {
		return ROWDY_CROWD_PATH . 'modules/' . $this->get_id() . '/' . ltrim( $relative_path, '/' );
	}

	/**
	 * Public URL to a file inside this module's folder.
	 *
	 * @param string $relative_path Path relative to the module folder.
	 */
	protected function url( string $relative_path = '' ): string {
		return ROWDY_CROWD_URL . 'modules/' . $this->get_id() . '/' . ltrim( $relative_path, '/' );
	}
}
