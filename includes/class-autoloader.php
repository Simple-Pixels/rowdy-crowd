<?php
/**
 * Class autoloader.
 *
 * @package RowdyCrowd
 */

namespace RowdyCrowd\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Maps plugin classes to files using WordPress file naming conventions.
 *
 * RowdyCrowd\Core\Plugin                 => includes/class-plugin.php
 * RowdyCrowd\Modules\Hello_World\Module  => modules/hello-world/class-module.php
 */
final class Autoloader {

	private const NAMESPACE_PREFIX = 'RowdyCrowd\\';

	/**
	 * Top-level namespace segment => base directory (relative to the plugin root).
	 */
	private const BASE_DIRECTORIES = array(
		'Core'    => 'includes',
		'Modules' => 'modules',
	);

	/**
	 * Registers the autoloader with SPL.
	 */
	public static function register(): void {
		spl_autoload_register( array( self::class, 'load' ) );
	}

	/**
	 * Loads the file for a class, if it belongs to this plugin.
	 *
	 * @param string $class_name Fully qualified class name.
	 */
	public static function load( string $class_name ): void {
		if ( ! str_starts_with( $class_name, self::NAMESPACE_PREFIX ) ) {
			return;
		}

		$segments = explode( '\\', substr( $class_name, strlen( self::NAMESPACE_PREFIX ) ) );
		$root     = array_shift( $segments );

		if ( ! isset( self::BASE_DIRECTORIES[ $root ] ) || empty( $segments ) ) {
			return;
		}

		$class       = array_pop( $segments );
		$directories = array_map( array( self::class, 'to_slug' ), $segments );
		$path        = implode( '/', array_merge( array( self::BASE_DIRECTORIES[ $root ] ), $directories ) );
		$file        = ROWDY_CROWD_PATH . $path . '/class-' . self::to_slug( $class ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Converts a class or namespace segment to a file slug (Hello_World => hello-world).
	 *
	 * @param string $name Class or namespace segment.
	 */
	private static function to_slug( string $name ): string {
		return strtolower( str_replace( '_', '-', $name ) );
	}
}
