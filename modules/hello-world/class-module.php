<?php
/**
 * Hello World module.
 *
 * @package RowdyCrowd
 */

namespace RowdyCrowd\Modules\Hello_World;

use RowdyCrowd\Core\Module as Base_Module;

defined( 'ABSPATH' ) || exit;

/**
 * Confirms the plugin is deployed and running.
 */
final class Module extends Base_Module {

	public function get_id(): string {
		return 'hello-world';
	}

	public function register(): void {
		add_action( 'admin_notices', array( $this, 'render_admin_notice' ) );
		add_shortcode( 'rowdy_crowd_hello', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Shows a notice to administrators with the deployed version.
	 */
	public function render_admin_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-success"><p>%s</p></div>',
			esc_html( $this->get_message() )
		);
	}

	/**
	 * Output for the [rowdy_crowd_hello] shortcode.
	 */
	public function render_shortcode(): string {
		return '<p class="rowdy-crowd-hello">' . esc_html( $this->get_message() ) . '</p>';
	}

	private function get_message(): string {
		/* translators: %s: plugin version number. */
		return sprintf( __( 'Hello World from Rowdy Crowd (v%s).', 'rowdy-crowd' ), ROWDY_CROWD_VERSION );
	}
}
