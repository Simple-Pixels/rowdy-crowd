<?php
/**
 * Geolocation module.
 *
 * @package RowdyCrowd
 */

namespace RowdyCrowd\Modules\Geolocation;

use RowdyCrowd\Core\Module as Base_Module;

defined( 'ABSPATH' ) || exit;

/**
 * Serves the AU and US versions of the site from one install, one per domain.
 *
 * - Elements with the class "aunz" show only in the AU region; "usa" only in the US region.
 * - First-time visitors are redirected to their region's domain based on Kinsta IP geolocation.
 * - Visitors can change region with the [region_switcher] shortcode.
 */
final class Module extends Base_Module {

	private const REDIRECT_TIMEOUT_MS = 2500;

	public function get_id(): string {
		return 'geolocation';
	}

	public function register(): void {
		add_filter( 'option_home', array( $this, 'filter_site_url' ), 20 );
		add_filter( 'option_siteurl', array( $this, 'filter_site_url' ), 20 );
		add_filter( 'allowed_redirect_hosts', array( $this, 'allow_region_hosts' ) );
		add_filter( 'body_class', array( $this, 'add_region_body_class' ) );
		add_action( 'template_redirect', array( $this, 'handle_region_choice' ), 1 );
		add_action( 'wp_head', array( $this, 'print_head_assets' ), 1 );
		add_action( 'wp_head', array( $this, 'print_hreflang_links' ) );
		add_action( 'rest_api_init', array( new Rest_Controller(), 'register_routes' ) );

		( new Switcher( $this->url( 'assets/' ) ) )->register();
	}

	/**
	 * Serves home and site URLs on whichever mapped domain was requested, so links,
	 * assets and canonical redirects stay on the current region's domain.
	 *
	 * @param mixed $url Stored URL.
	 * @return mixed
	 */
	public function filter_site_url( $url ) {
		$region = Region::host_region();
		$host   = is_string( $url ) ? wp_parse_url( $url, PHP_URL_HOST ) : null;

		if ( null === $region || ! $host ) {
			return $url;
		}

		return preg_replace( '#//' . preg_quote( $host, '#' ) . '#', '//' . Region::domains()[ $region ], $url, 1 );
	}

	/**
	 * Lets wp_safe_redirect() move visitors between region domains.
	 *
	 * @param string[] $hosts Allowed hosts.
	 * @return string[]
	 */
	public function allow_region_hosts( array $hosts ): array {
		return array_merge( $hosts, array_values( Region::domains() ) );
	}

	/**
	 * Adds rowdy-crowd-region-{region} to the body classes. The visibility CSS keys off it.
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public function add_region_body_class( array $classes ): array {
		$classes[] = 'rowdy-crowd-region-' . Region::current();

		return $classes;
	}

	/**
	 * Saves a region chosen through ?rc_region= and redirects to the clean URL,
	 * or to the region's own domain when it differs from the current one.
	 */
	public function handle_region_choice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display preference only, no state change beyond a cookie.
		$region = isset( $_GET[ Region::QUERY_VAR ] ) ? sanitize_key( wp_unslash( $_GET[ Region::QUERY_VAR ] ) ) : '';

		if ( ! Region::is_valid( $region ) ) {
			return;
		}

		Region::remember( $region );
		nocache_headers();

		$host_region = Region::host_region();
		$switching   = null !== $host_region && $host_region !== $region;

		wp_safe_redirect( Region::url_for( $region, $switching ) );
		exit;
	}

	/**
	 * Prints the visibility CSS and, on mapped domains, the first-visit redirect script.
	 */
	public function print_head_assets(): void {
		if ( $this->is_builder_preview() ) {
			// Show everything in the page builder, outlined by region.
			$css = '.aunz{outline:2px dashed #00a19a}.usa{outline:2px dashed #e6007e}';
		} else {
			$css = '.rowdy-crowd-region-aunz .usa,.rowdy-crowd-region-usa .aunz{display:none!important}'
				. 'html.rowdy-crowd-geolocating{visibility:hidden}';
		}

		printf( '<style id="rowdy-crowd-geolocation">%s</style>', $css ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static CSS.

		if ( null === Region::host_region() || $this->is_builder_preview() || is_customize_preview() ) {
			return;
		}

		$config = array(
			'region'   => Region::current(),
			'regions'  => array_keys( Region::all() ),
			'domains'  => Region::domains(),
			'cookie'   => Region::COOKIE,
			'queryVar' => Region::QUERY_VAR,
			'endpoint' => rest_url( Rest_Controller::ROUTE_NAMESPACE . '/region' ),
			'timeout'  => self::REDIRECT_TIMEOUT_MS,
		);

		$script = file_get_contents( $this->path( 'assets/redirect.js' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.

		wp_print_inline_script_tag(
			'window.rowdyCrowdGeolocation = ' . wp_json_encode( $config ) . ";\n" . $script,
			array( 'id' => 'rowdy-crowd-geolocation-redirect' )
		);
	}

	/**
	 * Tells search engines the AU and US domains are regional versions of the same page.
	 */
	public function print_hreflang_links(): void {
		if ( null === Region::host_region() || is_404() ) {
			return;
		}

		foreach ( array_keys( Region::domains() ) as $region ) {
			printf(
				'<link rel="alternate" hreflang="%s" href="%s" />' . "\n",
				esc_attr( Region::hreflang( $region ) ),
				esc_url( Region::url_for( $region, false ) )
			);
		}

		printf(
			'<link rel="alternate" hreflang="x-default" href="%s" />' . "\n",
			esc_url( Region::url_for( Region::DEFAULT_REGION, false ) )
		);
	}

	/**
	 * Whether the page is loading inside the Elementor editor.
	 */
	private function is_builder_preview(): bool {
		return isset( $_GET['elementor-preview'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check.
	}
}
