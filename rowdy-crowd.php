<?php
/**
 * Plugin Name:       Rowdy Crowd
 * Plugin URI:        https://rowdycrowd.com.au/
 * Description:       Custom functionality for the Rowdy Crowd WooCommerce site: product renderer, cart flow, quoting, geolocation and more.
 * Version:           0.2.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Rowdy Crowd
 * Text Domain:       rowdy-crowd
 * License:           Proprietary
 *
 * @package RowdyCrowd
 */

defined( 'ABSPATH' ) || exit;

define( 'ROWDY_CROWD_VERSION', '0.2.0' );
define( 'ROWDY_CROWD_FILE', __FILE__ );
define( 'ROWDY_CROWD_PATH', plugin_dir_path( __FILE__ ) );
define( 'ROWDY_CROWD_URL', plugin_dir_url( __FILE__ ) );

require_once ROWDY_CROWD_PATH . 'includes/class-autoloader.php';

RowdyCrowd\Core\Autoloader::register();

add_action( 'plugins_loaded', array( RowdyCrowd\Core\Plugin::instance(), 'boot' ) );
