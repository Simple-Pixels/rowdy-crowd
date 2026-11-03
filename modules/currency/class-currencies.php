<?php
/**
 * WooPayments Multi-Currency wrapper.
 *
 * @package RowdyCrowd
 */

namespace RowdyCrowd\Modules\Currency;

defined( 'ABSPATH' ) || exit;

/**
 * The only place that talks to WooPayments Multi-Currency, so a change in that plugin
 * only needs fixing here. Every method is safe to call when WooPayments isn't active.
 */
final class Currencies {

	/**
	 * Query var WooPayments uses to switch currency, e.g. ?currency=EUR.
	 * WooPayments saves the choice in the visitor's session (or user meta when logged in).
	 */
	public const QUERY_VAR = 'currency';

	/**
	 * Whether WooPayments Multi-Currency is available.
	 */
	public static function is_available(): bool {
		return function_exists( 'WC_Payments_Multi_Currency' );
	}

	/**
	 * Currencies enabled in WooPayments, keyed by code, in WooPayments' order (store currency first).
	 *
	 * @return array<string, array{name: string, symbol: string}>
	 */
	public static function enabled(): array {
		if ( ! self::is_available() ) {
			return array();
		}

		$currencies = array();

		foreach ( \WC_Payments_Multi_Currency()->get_enabled_currencies() as $code => $currency ) {
			$currencies[ $code ] = array(
				'name'   => $currency->get_name(),
				// WooCommerce returns symbols as HTML entities (e.g. "&#36;"); escaping happens on output.
				'symbol' => html_entity_decode( $currency->get_symbol(), ENT_QUOTES, 'UTF-8' ),
			);
		}

		return $currencies;
	}

	/**
	 * Whether a currency code is enabled in WooPayments.
	 *
	 * @param string $code ISO 4217 currency code.
	 */
	public static function is_enabled( string $code ): bool {
		return array_key_exists( $code, self::enabled() );
	}

	/**
	 * The visitor's currency code, or '' when WooPayments isn't available.
	 */
	public static function selected(): string {
		return self::is_available() ? \WC_Payments_Multi_Currency()->get_selected_currency()->get_code() : '';
	}
}
