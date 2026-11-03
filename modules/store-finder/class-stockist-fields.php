<?php
/**
 * Stockist fields.
 *
 * @package RowdyCrowd
 */

namespace RowdyCrowd\Modules\Store_Finder;

use RowdyCrowd\Modules\Geolocation\Region;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the stockist fields with Advanced Custom Fields. They are defined in code,
 * so they ship with the plugin and can't be edited or lost from the ACF screen.
 *
 * The store name is the post title. The Google Map field is the single source for the pin,
 * suburb, state and postcode, so an address is only entered once.
 */
final class Stockist_Fields {

	public const LOCATION = 'rowdy_crowd_location';
	public const ADDRESS  = 'rowdy_crowd_address';
	public const PHONE    = 'rowdy_crowd_phone';
	public const WEBSITE  = 'rowdy_crowd_website';
	public const REGION   = 'rowdy_crowd_region';

	public function register(): void {
		add_action( 'acf/init', array( $this, 'register_field_group' ) );
	}

	public function register_field_group(): void {
		acf_add_local_field_group(
			array(
				'key'      => 'group_rowdy_crowd_stockist',
				'title'    => __( 'Stockist details', 'rowdy-crowd' ),
				'position' => 'acf_after_title',
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => Stockist_Post_Type::POST_TYPE,
						),
					),
				),
				'fields'   => array(
					array(
						'key'          => 'field_rowdy_crowd_location',
						'label'        => __( 'Store location', 'rowdy-crowd' ),
						'name'         => self::LOCATION,
						'type'         => 'google_map',
						'instructions' => __( 'Search for the store and pick its address. The map pin, suburb, state and postcode all come from this.', 'rowdy-crowd' ),
						'required'     => 1,
						'center_lat'   => '-25.2744',
						'center_lng'   => '133.7751',
						'zoom'         => 4,
						'height'       => 360,
					),
					array(
						'key'          => 'field_rowdy_crowd_address',
						'label'        => __( 'Street address', 'rowdy-crowd' ),
						'name'         => self::ADDRESS,
						'type'         => 'text',
						'instructions' => __( 'Optional. Replaces the street part of the address shown to customers, for example "Shop 2, 76 George Street". Leave blank to use the address from the map.', 'rowdy-crowd' ),
					),
					array(
						'key'   => 'field_rowdy_crowd_phone',
						'label' => __( 'Phone', 'rowdy-crowd' ),
						'name'  => self::PHONE,
						'type'  => 'text',
					),
					array(
						'key'          => 'field_rowdy_crowd_website',
						'label'        => __( 'Website', 'rowdy-crowd' ),
						'name'         => self::WEBSITE,
						'type'         => 'url',
						'instructions' => __( 'Include https://', 'rowdy-crowd' ),
					),
					array(
						'key'           => 'field_rowdy_crowd_region',
						'label'         => __( 'Region', 'rowdy-crowd' ),
						'name'          => self::REGION,
						'type'          => 'select',
						'instructions'  => __( 'Which site lists this store: Australia shows it on rowdycrowd.com.au, United States on rowdycrowd.com.', 'rowdy-crowd' ),
						'choices'       => Region::all(),
						'default_value' => Region::DEFAULT_REGION,
						'allow_null'    => 0,
						'return_format' => 'value',
					),
				),
			)
		);
	}
}
