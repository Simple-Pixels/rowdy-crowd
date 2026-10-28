<?php
/**
 * Main plugin class.
 *
 * @package RowdyCrowd
 */

namespace RowdyCrowd\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Boots the plugin and registers each feature module.
 */
final class Plugin {

	/**
	 * Module classes to load, in order. Add new modules here.
	 */
	private const MODULES = array(
		\RowdyCrowd\Modules\Hello_World\Module::class,
		\RowdyCrowd\Modules\Geolocation\Module::class,
	);

	private static ?Plugin $instance = null;

	/**
	 * Registered module instances, keyed by module ID.
	 *
	 * @var array<string, Module>
	 */
	private array $modules = array();

	/**
	 * Returns the shared plugin instance.
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Registers all enabled modules.
	 */
	public function boot(): void {
		/**
		 * Filters the list of module classes the plugin loads.
		 *
		 * @param string[] $modules Fully qualified module class names.
		 */
		$module_classes = apply_filters( 'rowdy_crowd_modules', self::MODULES );

		foreach ( $module_classes as $module_class ) {
			$module = new $module_class();

			if ( ! $module instanceof Module || ! $module->is_enabled() ) {
				continue;
			}

			$module->register();
			$this->modules[ $module->get_id() ] = $module;
		}

		do_action( 'rowdy_crowd_loaded', $this );
	}

	/**
	 * Returns a registered module by ID, or null if it isn't loaded.
	 *
	 * @param string $id Module ID.
	 */
	public function get_module( string $id ): ?Module {
		return $this->modules[ $id ] ?? null;
	}
}
