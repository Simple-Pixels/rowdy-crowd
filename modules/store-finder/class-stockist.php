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
		public readonly string $region
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

		$street = trim( (string) get_field( Stockist_Fields::ADDRESS, $post_id ) );
		$region = (string) get_field( Stockist_Fields::REGION, $post_id );
		$state  = (string) ( $location['state_short'] ?? '' );

		return new self(
			$post_id,
			(string) get_post_field( 'post_title', $post_id ),
			(float) $location['lat'],
			(float) $location['lng'],
			'' !== $street ? $street : self::street_from_location( $location ),
			(string) ( $location['city'] ?? '' ),
			$state,
			(string) ( $location['state'] ?? $state ),
			(string) ( $location['post_code'] ?? '' ),
			trim( (string) get_field( Stockist_Fields::PHONE, $post_id ) ),
			trim( (string) get_field( Stockist_Fields::WEBSITE, $post_id ) ),
			Region::is_valid( $region ) ? $region : Region::DEFAULT_REGION
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
			implode( ' ', array( $this->name, $this->street, $this->suburb, $this->state_code, $this->state_name, $this->postcode ) ),
			'UTF-8'
		);
	}
}
