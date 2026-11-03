<?php
/**
 * Currency module.
 *
 * @package RowdyCrowd
 */

namespace RowdyCrowd\Modules\Currency;

use RowdyCrowd\Core\Module as Base_Module;

defined( 'ABSPATH' ) || exit;

/**
 * Lets visitors change currency with [currency_switcher]. WooPayments Multi-Currency does
 * the actual switching and pricing; this module adds the switcher and keeps URLs tidy.
 */
final class Module extends Base_Module {

	public function get_id(): string {
		return 'currency';
	}

	public function register(): void {
		// After the geolocation module's region handler (priority 1), which cleans its own URLs.
		add_action( 'template_redirect', array( $this, 'clean_currency_url' ), 2 );

		( new Switcher() )->register();
	}

	/**
	 * Redirects ?currency=XXX to the same URL without it.
	 *
	 * WooPayments has already saved the choice by the time this runs (it handles the query
	 * var on `init`), so the visitor lands on the clean URL in their new currency.
	 */
	public function clean_currency_url(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display preference only, nothing is changed by this check.
		$code = isset( $_GET[ Currencies::QUERY_VAR ] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET[ Currencies::QUERY_VAR ] ) ) ) : '';

		if ( '' === $code || ! Currencies::is_enabled( $code ) ) {
			return;
		}

		nocache_headers();
		wp_safe_redirect( remove_query_arg( Currencies::QUERY_VAR ) );
		exit;
	}
}
