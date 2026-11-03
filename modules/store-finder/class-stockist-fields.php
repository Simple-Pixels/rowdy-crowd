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
 * Defines the stockist fields and saves them to Advanced Custom Fields' database.
 *
 * A field group registered only in PHP is invisible to tools that read ACF's database, such as
 * the WP All Import ACF add-on. So the definition lives here, in code, and is saved to the
 * database as a normal ACF field group ("Stockist details"). It shows under ACF → Field Groups.
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

	private const GROUP_KEY      = 'group_rowdy_crowd_stockist';
	private const VERSION_OPTION = 'rowdy_crowd_stockist_fields_version';

	/**
	 * Version of the definition in definition(). Bump it whenever the fields change, so the
	 * change is saved to the database again (replacing any edits made on the ACF screen).
	 */
	private const VERSION = '1';

	public function register(): void {
		add_action( 'admin_init', array( $this, 'maybe_save_field_group' ) );
	}

	/**
	 * Saves the field group to ACF's database when it's missing or out of date.
	 *
	 * Runs for administrators only. After a deploy, the first wp-admin page an administrator
	 * opens does the saving.
	 */
	public function maybe_save_field_group(): void {
		if ( ! function_exists( 'acf_import_field_group' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$existing = acf_get_field_group_post( self::GROUP_KEY );

		if ( $existing && self::VERSION === get_option( self::VERSION_OPTION ) ) {
			return;
		}

		// ACF's importer updates a group in place only if it's given the existing group's ID.
		$group       = $this->definition();
		$group['ID'] = $existing ? $existing->ID : 0;

		acf_import_field_group( $group );
		update_option( self::VERSION_OPTION, self::VERSION );
	}

	/**
	 * The field group, in the format ACF imports.
	 *
	 * @return array<string, mixed>
	 */
	private function definition(): array {
		return array(
			'key'      => self::GROUP_KEY,
			'title'    => __( 'Stockist details', 'rowdy-crowd' ),
			'position' => 'acf_after_title',
			'active'   => true,
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
		);
	}
}
