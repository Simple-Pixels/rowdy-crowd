<?php
/**
 * WP Rocket compatibility.
 *
 * @package RowdyCrowd
 */

namespace RowdyCrowd\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the plugin's own scripts and styles out of WP Rocket's minify cache.
 *
 * WP Rocket copies each file into wp-content/cache/min and can keep serving that copy after the
 * original changes. Visitors then get a new page with the old script: the store finder went
 * live like that, with working buttons for the logged-in admin and dead ones for everyone
 * else. Excluded files are served as they are, with the plugin version in their URL, so a new
 * version always means a new file. They are small, so there is little to lose.
 *
 * This does nothing when WP Rocket isn't active.
 */
final class Wp_Rocket {

	public function register(): void {
		add_filter( 'rocket_exclude_js', array( $this, 'exclude_js' ) );
		add_filter( 'rocket_exclude_css', array( $this, 'exclude_css' ) );
	}

	/**
	 * @param mixed $files Patterns WP Rocket already excludes.
	 * @return string[]
	 */
	public function exclude_js( $files ): array {
		$files   = (array) $files;
		$files[] = $this->pattern( 'js' );

		return $files;
	}

	/**
	 * @param mixed $files Patterns WP Rocket already excludes.
	 * @return string[]
	 */
	public function exclude_css( $files ): array {
		$files   = (array) $files;
		$files[] = $this->pattern( 'css' );

		return $files;
	}

	/**
	 * A regex fragment matching every file of one type in this plugin, as WP Rocket expects:
	 * it is matched against each file's URL path, e.g. /wp-content/plugins/rowdy-crowd/....
	 *
	 * @param string $extension File extension without the dot.
	 */
	private function pattern( string $extension ): string {
		return preg_quote( (string) wp_parse_url( ROWDY_CROWD_URL, PHP_URL_PATH ), '#' ) . '(.*)\.' . $extension;
	}
}
