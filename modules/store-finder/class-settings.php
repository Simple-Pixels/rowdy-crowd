<?php
/**
 * Store finder settings.
 *
 * @package RowdyCrowd
 */

namespace RowdyCrowd\Modules\Store_Finder;

defined( 'ABSPATH' ) || exit;

/**
 * Stockists → Settings: Google Maps API key, Map ID and pin colour.
 *
 * The API key can instead be set with ROWDY_CROWD_GOOGLE_MAPS_API_KEY in wp-config.php.
 * That keeps it out of the database and takes priority over the saved value.
 */
final class Settings {

	private const OPTION            = 'rowdy_crowd_store_finder';
	private const PAGE              = 'rowdy-crowd-store-finder';
	private const GROUP             = 'rowdy_crowd_store_finder_group';
	private const DEFAULT_PIN_COLOR = '#E93CAC';
	private const DEMO_MAP_ID       = 'DEMO_MAP_ID';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'acf/init', array( $this, 'share_api_key_with_acf' ) );
	}

	public static function api_key(): string {
		if ( defined( 'ROWDY_CROWD_GOOGLE_MAPS_API_KEY' ) ) {
			return trim( (string) ROWDY_CROWD_GOOGLE_MAPS_API_KEY );
		}

		return self::get( 'api_key' );
	}

	/**
	 * Google Map ID. Advanced markers need one; Google's demo ID works until a real one is saved.
	 */
	public static function map_id(): string {
		$map_id = self::get( 'map_id' );

		return '' !== $map_id ? $map_id : self::DEMO_MAP_ID;
	}

	public static function pin_color(): string {
		$color = self::get( 'pin_color' );

		return '' !== $color ? $color : self::DEFAULT_PIN_COLOR;
	}

	private static function get( string $key ): string {
		$options = get_option( self::OPTION, array() );

		return is_array( $options ) && isset( $options[ $key ] ) ? (string) $options[ $key ] : '';
	}

	/**
	 * ACF's Google Map field needs the key to load its address search in the editor.
	 */
	public function share_api_key_with_acf(): void {
		$key = self::api_key();

		if ( '' !== $key ) {
			acf_update_setting( 'google_api_key', $key );
		}
	}

	public function add_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . Stockist_Post_Type::POST_TYPE,
			__( 'Store finder settings', 'rowdy-crowd' ),
			__( 'Settings', 'rowdy-crowd' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	public function register_settings(): void {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);

		add_settings_section( 'main', '', '__return_false', self::PAGE );

		add_settings_field( 'api_key', __( 'Google Maps API key', 'rowdy-crowd' ), array( $this, 'render_api_key_field' ), self::PAGE, 'main', array( 'label_for' => 'rowdy-crowd-api-key' ) );
		add_settings_field( 'map_id', __( 'Map ID', 'rowdy-crowd' ), array( $this, 'render_map_id_field' ), self::PAGE, 'main', array( 'label_for' => 'rowdy-crowd-map-id' ) );
		add_settings_field( 'pin_color', __( 'Pin colour', 'rowdy-crowd' ), array( $this, 'render_pin_color_field' ), self::PAGE, 'main', array( 'label_for' => 'rowdy-crowd-pin-color' ) );
	}

	/**
	 * @param mixed $input Submitted values.
	 * @return array<string, string>
	 */
	public function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();

		return array(
			'api_key'   => sanitize_text_field( $input['api_key'] ?? '' ),
			'map_id'    => (string) preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) ( $input['map_id'] ?? '' ) ),
			'pin_color' => (string) sanitize_hex_color( $input['pin_color'] ?? '' ),
		);
	}

	public function render_api_key_field(): void {
		$locked = defined( 'ROWDY_CROWD_GOOGLE_MAPS_API_KEY' );

		$this->render_input( 'rowdy-crowd-api-key', 'api_key', 'text', $locked ? '' : self::get( 'api_key' ), $locked );

		echo '<p class="description">';
		echo $locked
			? esc_html__( 'Set by ROWDY_CROWD_GOOGLE_MAPS_API_KEY in wp-config.php.', 'rowdy-crowd' )
			: esc_html__( 'Nothing map-related loads until this is set.', 'rowdy-crowd' );
		echo '</p>';
	}

	public function render_map_id_field(): void {
		$this->render_input( 'rowdy-crowd-map-id', 'map_id', 'text', self::get( 'map_id' ) );

		echo '<p class="description">' . esc_html__( 'Optional. Create one in Google Cloud Console (Google Maps Platform → Map Management). Until then a Google demo ID is used.', 'rowdy-crowd' ) . '</p>';
	}

	public function render_pin_color_field(): void {
		$this->render_input( 'rowdy-crowd-pin-color', 'pin_color', 'color', self::pin_color() );
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Store finder settings', 'rowdy-crowd' ); ?></h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>

			<h2><?php esc_html_e( 'Setup', 'rowdy-crowd' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'In Google Cloud Console, enable the Maps JavaScript API and the Places API.', 'rowdy-crowd' ); ?></li>
				<li><?php esc_html_e( 'Create an API key and restrict it by HTTP referrer to rowdycrowd.com.au/*, rowdycrowd.com/* and the staging domain.', 'rowdy-crowd' ); ?></li>
				<li><?php esc_html_e( 'Paste the key above and save. This also switches on the address search on the stockist screen.', 'rowdy-crowd' ); ?></li>
				<li><?php esc_html_e( 'Add stockists, then put [store_finder] on a page.', 'rowdy-crowd' ); ?></li>
			</ol>
		</div>
		<?php
	}

	/**
	 * Prints one settings input.
	 *
	 * @param string $id       Element ID.
	 * @param string $key      Key inside the option array.
	 * @param string $type     Input type.
	 * @param string $value    Current value.
	 * @param bool   $disabled Whether the input is read-only.
	 */
	private function render_input( string $id, string $key, string $type, string $value, bool $disabled = false ): void {
		printf(
			'<input type="%1$s" id="%2$s" name="%3$s[%4$s]" value="%5$s" class="%6$s"%7$s />',
			esc_attr( $type ),
			esc_attr( $id ),
			esc_attr( self::OPTION ),
			esc_attr( $key ),
			esc_attr( $value ),
			'color' === $type ? '' : 'regular-text',
			$disabled ? ' disabled' : ''
		);
	}
}
