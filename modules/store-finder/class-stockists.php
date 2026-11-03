<?php
/**
 * Stockist queries.
 *
 * @package RowdyCrowd
 */

namespace RowdyCrowd\Modules\Store_Finder;

use RowdyCrowd\Modules\Geolocation\Region;

defined( 'ABSPATH' ) || exit;

/**
 * Finds published stockists. Stores without a map location are left out, since they
 * can't be pinned.
 */
final class Stockists {

	/**
	 * Published stockists with a map location, sorted by state, suburb and name.
	 *
	 * @param string $region Region key, or '' for every region.
	 * @return Stockist[]
	 */
	public static function for_region( string $region ): array {
		$args = array(
			'post_type'      => Stockist_Post_Type::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		);

		if ( '' !== $region ) {
			$args['meta_query'] = self::region_meta_query( $region ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- A few hundred posts at most, and the result is page-cached.
		}

		$ids = get_posts( $args );

		// One query for all the fields, instead of one per stockist.
		update_meta_cache( 'post', $ids );

		$stockists = array_filter( array_map( array( Stockist::class, 'from_post' ), $ids ) );

		usort(
			$stockists,
			static fn ( Stockist $a, Stockist $b ): int => strnatcasecmp(
				$a->state_name . '|' . $a->suburb . '|' . $a->name,
				$b->state_name . '|' . $b->suburb . '|' . $b->name
			)
		);

		return $stockists;
	}

	/**
	 * The states the given stockists are in, for the filter. Code => name, sorted by name.
	 *
	 * @param Stockist[] $stockists Stockists.
	 * @return array<string, string>
	 */
	public static function states( array $stockists ): array {
		$states = array();

		foreach ( $stockists as $stockist ) {
			if ( '' !== $stockist->state_code ) {
				$states[ $stockist->state_code ] = $stockist->state_name;
			}
		}

		asort( $states, SORT_NATURAL | SORT_FLAG_CASE );

		return $states;
	}

	/**
	 * How many of the given stockists are in each state. Code => count.
	 *
	 * @param Stockist[] $stockists Stockists.
	 * @return array<string, int>
	 */
	public static function state_counts( array $stockists ): array {
		$counts = array();

		foreach ( $stockists as $stockist ) {
			if ( '' !== $stockist->state_code ) {
				$counts[ $stockist->state_code ] = ( $counts[ $stockist->state_code ] ?? 0 ) + 1;
			}
		}

		return $counts;
	}

	/**
	 * Brand colour for a state's filter chip: pink, teal, orange, lime or blue.
	 *
	 * Australian states have fixed colours so each keeps its own. Any other state gets a stable
	 * colour worked out from its code.
	 *
	 * @param string $state_code State code, e.g. "NSW".
	 */
	public static function chip_colour( string $state_code ): string {
		$fixed   = array(
			'NSW' => 'blue',
			'ACT' => 'pink',
			'VIC' => 'teal',
			'QLD' => 'orange',
			'SA'  => 'lime',
			'WA'  => 'pink',
			'TAS' => 'blue',
			'NT'  => 'lime',
		);
		$palette = array( 'pink', 'teal', 'orange', 'lime', 'blue' );

		return $fixed[ $state_code ] ?? $palette[ abs( crc32( $state_code ) ) % count( $palette ) ];
	}

	/**
	 * Stockists with no region saved (e.g. imported ones) count as the default region.
	 *
	 * @param string $region Region key.
	 * @return array<int|string, mixed>
	 */
	private static function region_meta_query( string $region ): array {
		$matches = array(
			'key'   => Stockist_Fields::REGION,
			'value' => $region,
		);

		if ( Region::DEFAULT_REGION !== $region ) {
			return array( $matches );
		}

		return array(
			'relation' => 'OR',
			$matches,
			array(
				'key'     => Stockist_Fields::REGION,
				'compare' => 'NOT EXISTS',
			),
		);
	}
}
