<?php
/**
 * Region resolution.
 *
 * @package RowdyCrowd
 */

namespace RowdyCrowd\Modules\Geolocation;

defined( 'ABSPATH' ) || exit;

/**
 * Works out which region (AU/NZ or USA) the current request belongs to.
 *
 * On a mapped domain the domain decides the region, which keeps page caching
 * correct. On any other host (staging, temporary URLs) the visitor's saved
 * choice is used, falling back to the default region.
 */
final class Region {

	public const AUNZ           = 'aunz';
	public const USA            = 'usa';
	public const DEFAULT_REGION = self::AUNZ;

	/**
	 * Cookie holding the visitor's chosen region. Read by the redirect script, so not HttpOnly.
	 */
	public const COOKIE = 'rowdy_crowd_region';

	/**
	 * Query var used by the switcher to set a region, e.g. ?rc_region=usa.
	 */
	public const QUERY_VAR = 'rc_region';

	private const DOMAINS = array(
		self::AUNZ => 'rowdycrowd.com.au',
		self::USA  => 'rowdycrowd.com',
	);

	/**
	 * Countries (ISO 3166-1 alpha-2) routed to the USA region. Everyone else gets AU/NZ.
	 */
	private const USA_COUNTRIES = array( 'US', 'CA', 'MX' );

	private const HREFLANG = array(
		self::AUNZ => 'en-AU',
		self::USA  => 'en-US',
	);

	/**
	 * All regions with their display labels.
	 *
	 * @return array<string, string>
	 */
	public static function all(): array {
		return array(
			self::AUNZ => __( 'Australia', 'rowdy-crowd' ),
			self::USA  => __( 'United States', 'rowdy-crowd' ),
		);
	}

	/**
	 * Whether a value is a known region key.
	 *
	 * @param mixed $region Value to check.
	 */
	public static function is_valid( $region ): bool {
		return is_string( $region ) && array_key_exists( $region, self::all() );
	}

	/**
	 * Display label for a region.
	 *
	 * @param string $region Region key.
	 */
	public static function label( string $region ): string {
		return self::all()[ $region ] ?? '';
	}

	/**
	 * Hreflang code for a region.
	 *
	 * @param string $region Region key.
	 */
	public static function hreflang( string $region ): string {
		return self::HREFLANG[ $region ] ?? '';
	}

	/**
	 * Region => domain map.
	 *
	 * @return array<string, string>
	 */
	public static function domains(): array {
		/**
		 * Filters the domain used for each region. Useful for mapping staging domains.
		 *
		 * @param array<string, string> $domains Region key => bare domain (no scheme or www).
		 */
		return apply_filters( 'rowdy_crowd_region_domains', self::DOMAINS );
	}

	/**
	 * The requested host, lowercased, without port or leading "www.".
	 */
	public static function current_host(): string {
		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$host = strtolower( preg_replace( '/:\d+$/', '', $host ) );

		return preg_replace( '/^www\./', '', $host );
	}

	/**
	 * Region the current host is mapped to, or null on an unmapped host.
	 */
	public static function host_region(): ?string {
		$region = array_search( self::current_host(), self::domains(), true );

		return false === $region ? null : $region;
	}

	/**
	 * Region for the current request: mapped domain, then saved choice, then the default.
	 */
	public static function current(): string {
		$region = self::host_region() ?? self::saved();

		if ( ! self::is_valid( $region ) ) {
			$region = self::DEFAULT_REGION;
		}

		/**
		 * Filters the region for the current request.
		 *
		 * @param string $region Region key.
		 */
		return apply_filters( 'rowdy_crowd_region', $region );
	}

	/**
	 * The visitor's saved region choice, if any.
	 */
	public static function saved(): ?string {
		$region = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : null;

		return self::is_valid( $region ) ? $region : null;
	}

	/**
	 * Saves the visitor's region choice for this domain.
	 *
	 * @param string $region Region key.
	 */
	public static function remember( string $region ): void {
		if ( headers_sent() ) {
			return;
		}

		setcookie(
			self::COOKIE,
			$region,
			array(
				'expires'  => time() + YEAR_IN_SECONDS,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'secure'   => is_ssl(),
				'httponly' => false,
				'samesite' => 'Lax',
			)
		);

		$_COOKIE[ self::COOKIE ] = $region;
	}

	/**
	 * Visitor's two-letter country code from Kinsta's IP geolocation, or '' if unknown.
	 *
	 * Define ROWDY_CROWD_TEST_COUNTRY in wp-config.php to fake a country while testing.
	 */
	public static function visitor_country(): string {
		if ( defined( 'ROWDY_CROWD_TEST_COUNTRY' ) ) {
			$country = (string) ROWDY_CROWD_TEST_COUNTRY;
		} else {
			$country = isset( $_SERVER['GEOIP_COUNTRY_CODE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['GEOIP_COUNTRY_CODE'] ) ) : '';
		}

		$country = strtoupper( $country );

		/**
		 * Filters the detected visitor country.
		 *
		 * @param string $country ISO 3166-1 alpha-2 code, or '' if unknown.
		 */
		$country = apply_filters( 'rowdy_crowd_visitor_country', $country );

		return preg_match( '/^[A-Z]{2}$/', $country ) ? $country : '';
	}

	/**
	 * Region a country belongs to. Unknown countries get the default region.
	 *
	 * @param string $country ISO 3166-1 alpha-2 code.
	 */
	public static function from_country( string $country ): string {
		/**
		 * Filters the countries routed to the USA region.
		 *
		 * @param string[] $countries ISO 3166-1 alpha-2 codes.
		 */
		$usa_countries = apply_filters( 'rowdy_crowd_usa_countries', self::USA_COUNTRIES );

		return in_array( $country, $usa_countries, true ) ? self::USA : self::DEFAULT_REGION;
	}

	/**
	 * URL of the current page in another region.
	 *
	 * On mapped domains this points at the region's domain. Elsewhere it stays on the
	 * current host. With $with_choice the URL carries ?rc_region= so the choice is saved.
	 *
	 * @param string $region      Region key.
	 * @param bool   $with_choice Whether to add the region query var.
	 */
	public static function url_for( string $region, bool $with_choice = true ): string {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$request_uri = remove_query_arg( self::QUERY_VAR, $request_uri );
		$domains     = self::domains();

		if ( null !== self::host_region() && isset( $domains[ $region ] ) ) {
			$url = set_url_scheme( 'http://' . $domains[ $region ] . $request_uri );
		} else {
			$url = home_url( $request_uri );
		}

		return $with_choice ? add_query_arg( self::QUERY_VAR, $region, $url ) : $url;
	}
}
