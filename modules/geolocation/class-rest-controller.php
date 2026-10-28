<?php
/**
 * Region REST endpoint.
 *
 * @package RowdyCrowd
 */

namespace RowdyCrowd\Modules\Geolocation;

use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * GET /wp-json/rowdy-crowd/v1/region
 *
 * Returns the visitor's country and suggested region. Used by the redirect script,
 * because cached pages can't run PHP per visitor.
 */
final class Rest_Controller {

	public const ROUTE_NAMESPACE = 'rowdy-crowd/v1';

	public function register_routes(): void {
		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/region',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_region' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function get_region(): WP_REST_Response {
		$country  = Region::visitor_country();
		$response = new WP_REST_Response(
			array(
				'country' => $country,
				'region'  => Region::from_country( $country ),
			)
		);

		$response->header( 'Cache-Control', 'no-store, private' );

		return $response;
	}
}
