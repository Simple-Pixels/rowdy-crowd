<?php
/**
 * Currency switcher shortcode.
 *
 * @package RowdyCrowd
 */

namespace RowdyCrowd\Modules\Currency;

use RowdyCrowd\Core\Dropdown;

defined( 'ABSPATH' ) || exit;

/**
 * [currency_switcher] — a dropdown listing every currency enabled in WooPayments.
 *
 * Each option links to the current page with ?currency=XXX, which WooPayments handles.
 * Outputs nothing when WooPayments Multi-Currency is off or has fewer than two currencies.
 */
final class Switcher {

	public const SHORTCODE = 'currency_switcher';

	public function register(): void {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
		Dropdown::register();
	}

	public function render(): string {
		$currencies = Currencies::enabled();

		if ( count( $currencies ) < 2 ) {
			return '';
		}

		$selected = Currencies::selected();
		$options  = array();

		foreach ( $currencies as $code => $currency ) {
			$options[] = array(
				'label'   => sprintf( '%1$s (%2$s %3$s)', $currency['name'], $currency['symbol'], $code ),
				'url'     => add_query_arg( Currencies::QUERY_VAR, $code ),
				'current' => $code === $selected,
			);
		}

		return Dropdown::render(
			'rowdy-crowd-currency-switcher',
			$selected,
			/* translators: %s: current currency code, e.g. AUD. */
			sprintf( __( 'Currency: %s. Change currency', 'rowdy-crowd' ), $selected ),
			$options
		);
	}
}
