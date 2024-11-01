<?php

/**
 * PoloPag Pagamentos
 *
 * @link              https://polopag.com
 * @since             1.0.0
 * @package           PoloPagPayments
 *
 * @wordpress-plugin
 * Plugin Name:       		PoloPag Pagamentos
 * Description:       		Potencialize suas vendas online com o PoloPag, oferecendo uma experiência de compra excepcional aos seus clientes.
 * Version:           		2.0.5
 * Requires at least: 		5.2
 * Requires PHP:      		7.0
 * WC requires at least:	8.0
 * WC tested up to:      	9.2.3
 * Author:            		PoloPag
 * Author URI:        		https://polopag.com
 * Text Domain:       		wc-polo-payments
 * License:           		GPLv2 or later
 * License URI:       		http://www.gnu.org/licenses/gpl-2.0.txt
 */
defined( 'ABSPATH' ) || exit;

//Define globals
define( 'POLOPAGPAYMENTS_PLUGIN_NAME', 'wc-polo-payments' );
define( 'POLOPAGPAYMENTS_PLUGIN_VERSION', '2.0.5' );
define( 'POLOPAGPAYMENTS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'POLOPAGPAYMENTS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'POLOPAGPAYMENTS_BASE_NAME', plugin_basename( __FILE__ ) );
define( 'POLOPAGPAYMENTS_DIR_NAME', dirname( plugin_basename( __FILE__ ) ) );
define( 'POLOPAGPAYMENTS_FILE_NAME', __FILE__ );

function polopagpayments_deactivate_plugin() {
	$timestamp = wp_next_scheduled( 'polopagpayments_schedule' );
	wp_unschedule_event( $timestamp, 'polopagpayments_schedule' );

	$timestamp = wp_next_scheduled( 'polopagpayments_schedule_api' );
	wp_unschedule_event( $timestamp, 'polopagpayments_schedule_api' );
}
register_deactivation_hook( __FILE__, 'polopagpayments_deactivate_plugin' );

require POLOPAGPAYMENTS_PLUGIN_PATH . 'vendor/autoload.php';

/**
 * Initialize singleton's instance().
 *
 * @since 1.0.0
 *
 * @return PoloPagPayments\Core
 */
function polopagpayments_init() {
	/**
	 * @var \PoloPagPayments\Core
	 */
	static $core;

	if ( ! isset( $core ) ) {
		$core = new \PoloPagPayments\Core();
	}

	return $core;
}

polopagpayments_init();
