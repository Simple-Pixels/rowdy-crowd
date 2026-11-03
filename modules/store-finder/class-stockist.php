<?php
/**
 * Stockist value object.
 *
 * @package RowdyCrowd
 */

namespace RowdyCrowd\Modules\Store_Finder;

use RowdyCrowd\Modules\Geolocation\Region;

defined( 'ABSPATH' ) || exit;

/**
 * One store, read from a Stockist post and its ACF fields, with the display helpers
 * the list and map need.
 */
final class Stockist {

	private const AU_STATES = array(
		'ACT' => 'Australian Capital Territory',
		'NSW' => 'New South Wales',
		'NT'  => 'Northern Territory',
		'QLD' => 'Queensland',
		'SA'  => 'South Australia',
		'TAS' => 'Tasmania',
		'VIC' => 'Victoria',
		'WA'  => 'Western Australia',
	);

	public function __construct(
		public readonly int $id,
		public readonly string $name,
		public readonly float $lat,
		public readonly float $lng,
		public readonly string $street,
		public readonly string $suburb,
		public readonly string $state_code,
		public readonly string $state_name,
		public readonly string $postcode,
		public readonly string $phone,
		public readonly string $website,
		public readonly string $region,
		public readonly string $map_address
	) {}

	/**
	 * Reads a stockist, or returns null if it has no usable map location (or ACF is missing).
	 *
	 * @param int $post_id Stockist post ID.
	 */
	public static function from_post( int $post_id ): ?self {
		if ( ! function_exists( 'get_field' ) ) {
			return null;
		}

		$location = get_field( Stockist_Fields::LOCATION, $post_id );

		if ( ! is_array( $location ) || ! is_numeric( $location['lat'] ?? null ) || ! is_numeric( $location['lng'] ?? null ) ) {
			return null;
		}

		$street      = trim( (string) get_field( Stockist_Fields::ADDRESS, $post_id ) );
		$region      = (string) get_field( Stockist_Fields::REGION, $post_id );
		$map_address = (string) ( $location['address'] ?? '' );
		$parsed      = self::locality_from_address( $map_address );

		// Suburb, state and postcode: the stockist's own fields, then the map's parts, then
		// whatever can be read out of the map's address text.
		$suburb   = self::first( (string) get_field( Stockist_Fields::SUBURB, $post_id ), (string) ( $location['city'] ?? '' ), $parsed['suburb'] );
		$state    = strtoupper( self::first( (string) get_field( Stockist_Fields::STATE, $post_id ), (string) ( $location['state_short'] ?? '' ), $parsed['state'] ) );
		$postcode = self::first( (string) get_field( Stockist_Fields::POSTCODE, $post_id ), (string) ( $location['post_code'] ?? '' ), $parsed['postcode'] );

		return new self(
			$post_id,
			(string) get_post_field( 'post_title', $post_id ),
			(float) $location['lat'],
			(float) $location['lng'],
			'' !== $street ? $street : self::street_from_location( $location ),
			$suburb,
			$state,
			self::state_name( $state, $location ),
			$postcode,
			trim( (string) get_field( Stockist_Fields::PHONE, $post_id ) ),
			trim( (string) get_field( Stockist_Fields::WEBSITE, $post_id ) ),
			Region::is_valid( $region ) ? $region : Region::DEFAULT_REGION,
			$map_address
		);
	}

	/**
	 * The first value that isn't blank, or ''.
	 *
	 * @param string ...$values Candidates, best first.
	 */
	private static function first( string ...$values ): string {
		foreach ( $values as $value ) {
			if ( '' !== trim( $value ) ) {
				return trim( $value );
			}
		}

		return '';
	}

	/**
	 * Full state name for a code: the map's own name when it matches, else a known Australian
	 * state, else the code itself.
	 *
	 * @param string               $code     State code, e.g. "NSW".
	 * @param array<string, mixed> $location ACF Google Map value.
	 */
	private static function state_name( string $code, array $location ): string {
		if ( '' === $code ) {
			return '';
		}

		$map_name = (string) ( $location['state'] ?? '' );

		if ( '' !== $map_name && strtoupper( (string) ( $location['state_short'] ?? '' ) ) === $code ) {
			return $map_name;
		}

		return self::AU_STATES[ $code ] ?? $code;
	}

	/**
	 * Reads the suburb, state and postcode out of an address such as
	 * "76 George St, Bathurst NSW 2795, Australia" or "1 Main St, Austin, TX 78701, USA".
	 * Anything it can't find comes back blank.
	 *
	 * @param string $address Address text from the Google Map value.
	 * @return array{suburb: string, state: string, postcode: string}
	 */
	private static function locality_from_address( string $address ): array {
		$blank = array(
			'suburb'   => '',
			'state'    => '',
			'postcode' => '',
		);

		if ( ! preg_match( '/,\s*([^,]+?),?\s+([A-Z]{2,3})\s+(\d{4,5})\b/', $address, $matches ) ) {
			return $blank;
		}

		return array(
			'suburb'   => trim( $matches[1] ),
			'state'    => $matches[2],
			'postcode' => $matches[3],
		);
	}

	/**
	 * The street part of the address from the Google Map value, e.g. "76 George Street".
	 *
	 * @param array<string, mixed> $location ACF Google Map value.
	 */
	private static function street_from_location( array $location ): string {
		$street = trim( ( $location['street_number'] ?? '' ) . ' ' . ( $location['street_name'] ?? '' ) );

		if ( '' !== $street ) {
			return $street;
		}

		// No street number or name (e.g. a shopping centre): use the first part of the full address.
		$parts = explode( ',', (string) ( $location['address'] ?? '' ) );

		return trim( (string) reset( $parts ) );
	}

	/**
	 * Full address on one line, e.g. "76 George Street, Bathurst NSW 2795".
	 */
	public function address(): string {
		$locality = implode( ' ', array_filter( array( $this->suburb, $this->state_code, $this->postcode ) ) );

		return implode( ', ', array_filter( array( $this->street, $locality ) ) );
	}

	/**
	 * A tel: link for the phone number.
	 */
	public function phone_href(): string {
		return 'tel:' . preg_replace( '/[^0-9+]/', '', $this->phone );
	}

	/**
	 * The website without its scheme, www. or trailing slash, e.g. "seasontotaste.au".
	 */
	public function website_label(): string {
		return (string) preg_replace( '#^https?://(www\.)?#i', '', rtrim( $this->website, '/' ) );
	}

	/**
	 * Google Maps directions to the store.
	 */
	public function directions_url(): string {
		return 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode( $this->lat . ',' . $this->lng );
	}

	/**
	 * Lowercase text the finder's search box matches against.
	 */
	public function search_text(): string {
		return mb_strtolower(
			implode( ' ', array( $this->name, $this->street, $this->suburb, $this->state_code, $this->state_name, $this->postcode, $this->map_address ) ),
			'UTF-8'
		);
	}
}
