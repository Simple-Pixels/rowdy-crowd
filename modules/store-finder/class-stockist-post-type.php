<?php
/**
 * Stockist post type.
 *
 * @package RowdyCrowd
 */

namespace RowdyCrowd\Modules\Store_Finder;

use RowdyCrowd\Modules\Geolocation\Region;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Stockists post type: one post per store. It has an admin screen but no
 * pages of its own on the site, because stores only appear in the [store_finder] shortcode.
 */
final class Stockist_Post_Type {

	public const POST_TYPE = 'rc_stockist';

	public function register(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_filter( 'enter_title_here', array( $this, 'filter_title_placeholder' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'filter_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
	}

	public function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'               => __( 'Stockists', 'rowdy-crowd' ),
					'singular_name'      => __( 'Stockist', 'rowdy-crowd' ),
					'add_new'            => __( 'Add new', 'rowdy-crowd' ),
					'add_new_item'       => __( 'Add new stockist', 'rowdy-crowd' ),
					'edit_item'          => __( 'Edit stockist', 'rowdy-crowd' ),
					'new_item'           => __( 'New stockist', 'rowdy-crowd' ),
					'search_items'       => __( 'Search stockists', 'rowdy-crowd' ),
					'not_found'          => __( 'No stockists found.', 'rowdy-crowd' ),
					'not_found_in_trash' => __( 'No stockists found in the bin.', 'rowdy-crowd' ),
					'all_items'          => __( 'All stockists', 'rowdy-crowd' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'menu_icon'           => 'dashicons-store',
				'menu_position'       => 26,
				'supports'            => array( 'title' ),
			)
		);
	}

	/**
	 * The title is the store name.
	 *
	 * @param string  $placeholder Default placeholder.
	 * @param WP_Post $post        Post being edited.
	 */
	public function filter_title_placeholder( string $placeholder, WP_Post $post ): string {
		return self::POST_TYPE === $post->post_type ? __( 'Store name', 'rowdy-crowd' ) : $placeholder;
	}

	/**
	 * @param array<string, string> $columns Default columns.
	 * @return array<string, string>
	 */
	public function filter_columns( array $columns ): array {
		return array(
			'cb'                  => $columns['cb'] ?? '',
			'title'               => __( 'Store name', 'rowdy-crowd' ),
			'rowdy_crowd_address' => __( 'Address', 'rowdy-crowd' ),
			'rowdy_crowd_state'   => __( 'State', 'rowdy-crowd' ),
			'rowdy_crowd_region'  => __( 'Region', 'rowdy-crowd' ),
			'rowdy_crowd_phone'   => __( 'Phone', 'rowdy-crowd' ),
			'date'                => $columns['date'] ?? '',
		);
	}

	/**
	 * @param string $column  Column key.
	 * @param int    $post_id Stockist post ID.
	 */
	public function render_column( string $column, int $post_id ): void {
		if ( ! function_exists( 'get_field' ) ) {
			return;
		}

		$stockist = Stockist::from_post( $post_id );

		switch ( $column ) {
			case 'rowdy_crowd_address':
				echo null === $stockist
					? '<strong>' . esc_html__( 'No map pin: not shown on the site', 'rowdy-crowd' ) . '</strong>'
					: esc_html( $stockist->address() );
				break;

			case 'rowdy_crowd_state':
				echo esc_html( null === $stockist ? '' : $stockist->state_code );
				break;

			case 'rowdy_crowd_region':
				$region = (string) get_field( Stockist_Fields::REGION, $post_id );
				echo esc_html( Region::label( Region::is_valid( $region ) ? $region : Region::DEFAULT_REGION ) );
				break;

			case 'rowdy_crowd_phone':
				echo esc_html( (string) get_field( Stockist_Fields::PHONE, $post_id ) );
				break;
		}
	}
}
