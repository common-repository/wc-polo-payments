<?php

namespace PoloPagPayments\Gateway;

use Exception;
//use PoloPagPayments\WP\Debug;
use PoloPagPayments\WP\Helper as WP;
use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * Load gateway if woocommerce is available.
 *
 * @since      1.1.0 
 */
class BaseGateway {
	/**
	 * Add all actions and filters to configure woocommerce
	 * gateways.
	 * 
	 * @since 1.2.0
	 * @return void
	 */
	public static function init() {
		$base = new self();

		$base->expire_payment_scheduled();

		add_option( 'polopagpayments_last_check', time() );
		WP::add_filter( 'woocommerce_payment_gateways', $base, 'add_gateway' );
		WP::add_filter( 'plugin_action_links_' . \POLOPAGPAYMENTS_BASE_NAME, $base, 'plugin_action_links' );
		WP::add_action( 'wp_ajax_polopagpayments_check', $base, 'check_pix_payment' );
		WP::add_action( 'wp_ajax_nopriv_polopagpayments_check', $base, 'check_pix_payment' );
		WP::add_action( 'wp_loaded', $base, 'wp_loaded' );
		WP::add_action( 'woocommerce_cart_calculate_fees', $base, 'add_discount' );
	}

	public function wp_loaded() {
		WP::add_action( 'polopagpayments_schedule', $this, 'check_expired_codes' );
		WP::add_action( 'polopagpayments_schedule_api', $this, 'check_api_order_status' );
		$this->check_cron();
	}

	public function check_api_order_status() {
		update_option( 'polopagpayments_last_check', time() );

		$gateways = WC()->payment_gateways()->get_available_payment_gateways();

		if ( ! isset( $gateways['polopagpayments_geteway'] ) )
			return;

		$gateway = $gateways['polopagpayments_geteway'];

		$args = array(
			'limit' => -1,
			'status' => array( 'on-hold', 'wc-pending' ),
			'type' => 'shop_order',
			'date_created' => '>' . ( time() - 86400 ),
			'payment_method' => 'polopagpayments_geteway'
		);

		$last_orders_24h = wc_get_orders( $args );

		foreach ( $last_orders_24h as $order ) {
			$taxid = $order->get_meta( '_polopagpayments_transaction_id' );
			if ( empty( $taxid ) )
				continue;

			$params['headers'] = [ 
				'Api-Key' => $gateway->api_key,
			];
			$response = wp_safe_remote_get( $gateway->api->get_api_url() . 'check-pix/' . $taxid, $params );
			if ( is_wp_error( $response ) )
				continue;

			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( ! isset( $data['status'] ) || $data['status'] != 'APROVADO' )
				continue;

			$gateway->api->process_order_status( $order, $data['status'] );
		}
	}

	/**
	 * Check payment ajax request
	 *
	 * @return void
	 */
	public function check_pix_payment() {
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'check_pix_nonce' ) ) {
			wp_send_json_error( esc_html__( 'Request refused.', 'wc-polo-payments' ), 403 );
			return;
		}

		$key = sanitize_text_field( $_GET['key'] );
		$order_id = wc_get_order_id_by_order_key( $key );
		$order = wc_get_order( $order_id );

		if ( $order ) {
			$paid = $order->get_meta( '_polopagpayments_paid' ) === 'yes' ? true : false;
			wp_send_json( [ 'paid' => $paid ] );
			return;
		}

		wp_send_json_error( esc_html__( 'Request refused.', 'wc-polo-payments' ), 403 );
		return;
	}

	/**
	 * If cron fail check if is a scheduled event and reschedule it
	 *
	 * @return void
	 */
	public function check_cron() {
		/**
		 * @var int|bool $last_check
		 */
		$last_check = get_option( 'polopagpayments_last_check' );
		if ( $last_check === false )
			return;

		if ( time() - $last_check > 140 ) {
			update_option( 'polopagpayments_last_check', time() );
			do_action( 'polopagpayments_schedule_api' );
		}
	}

	/**
	 * Check cron payments
	 *
	 * @return void
	 */
	public function expire_payment_scheduled() {
		if ( ! wp_next_scheduled( 'polopagpayments_schedule' ) ) {
			wp_schedule_event( time(), 'hourly', 'polopagpayments_schedule' );
		}
		if ( ! wp_next_scheduled( 'polopagpayments_schedule_api' ) ) {
			wp_schedule_event( time(), '2min', 'polopagpayments_schedule_api' );
		}
	}

	/**
	 * Check expired qr codes
	 *
	 * @return void
	 */
	public function check_expired_codes() {
		$plugin_options = maybe_unserialize( get_option( 'woocommerce_polopagpayments_geteway_settings', false ) );

		if ( ! ( $plugin_options && isset( $plugin_options['auto_cancel'] ) && $plugin_options['auto_cancel'] == 'yes' ) )
			return;

		$pix_orders = wc_get_orders(
			array(
				'limit' => -1,
				'type' => 'shop_order',
				'status' => array( 'on-hold' ),
				'payment_method' => 'polopagpayments_geteway'
			)
		);
		foreach ( $pix_orders as $order ) {
			$expiration_date = $order->get_meta( '_polopagpayments_expiration_date' );
			$date_format = 'Y-m-d H:i:s';

			if ( preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $expiration_date ) ) {
				$date_format = 'Y-m-d';
			} elseif ( ! preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/', $expiration_date ) ) {
				continue;
			}
			$expiration_date = \DateTime::createFromFormat( $date_format, $expiration_date );
			$current_date = \DateTime::createFromFormat( $date_format, gmdate( $date_format, strtotime( current_time( 'mysql' ) ) ) );
			if ( $current_date >= $expiration_date ) {
				$order->update_status( 'cancelled', 'PIX Polopag: QR Code expirado, cancelamento automático do pedido.' );
			}
		}
	}

	/**
	 * Add discount.
	 */
	public function add_discount( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) || is_cart() ) {
			return;
		}

		$plugin_options = maybe_unserialize( get_option( 'woocommerce_polopagpayments_geteway_settings', false ) );

		if (
			WC()->session->chosen_payment_method == 'polopagpayments_geteway' &&
			isset( $plugin_options['apply_discount'] ) && $plugin_options['apply_discount'] == 'yes' &&
			isset( $plugin_options['apply_discount_type'] ) && isset( $plugin_options['apply_discount_amount'] )
		) {

			$type = $plugin_options['apply_discount_type'];
			$amount = $plugin_options['apply_discount_amount'];

			if ( apply_filters( 'polopagpayments_apply_discount', 0 < $amount, $cart ) ) {
				$payment_gateways = WC()->payment_gateways->payment_gateways();
				$gateway = $payment_gateways['polopagpayments_geteway'];
				$name = sprintf( 'Desconto para %s %s', $gateway->title, $type == 'percentage' ? " ({$amount}%)" : '' );
				$discount_name = apply_filters( 'polopagpayments_apply_discount_name', $name );
				$cart_discount = $this->calculate_discount( $type, $amount, $cart->cart_contents_total ) * -1;
				$cart->add_fee( $discount_name, $cart_discount, true );
			}
		}
	}

	/**
	 * Calcule the discount amount.
	 */
	protected function calculate_discount( $type, $value, $subtotal ) {
		if ( $type == 'percentage' ) {
			$value = ( $subtotal / 100 ) * ( $value );
		}
		return $value;
	}

	/**
	 * Add gateways to Woocommerce.
	 * 
	 * @since 1.1.0
	 * @return array
	 */
	public function add_gateway( array $gateways ) {
		array_push( $gateways, PoloPixGateway::getInstance() );
		return $gateways;
	}

	/**
	 * Add links to plugin settings page.
	 * 
	 * @since 1.1.0
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$pluginLinks = array();

		$baseUrl = esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=polopagpayments_geteway' ) );

		$pluginLinks[] = sprintf( '<a href="%s">%s</a>', $baseUrl, __( 'Configurações', 'wc-polo-payments' ) );
		$pluginLinks[] = sprintf( '<a href="%s" target="_blank">%s</a>', 'https://wordpress.org/support/plugin/wc-polo-payments/', __( 'Suporte', 'wc-polo-payments' ) );
		$pluginLinks[] = sprintf( '<a href="%s" target="_blank">%s</a>', 'https://wordpress.org/plugins/wc-polo-payments/#reviews', __( 'Avalie o Plugin	', 'wc-polo-payments' ) );

		return array_merge( $pluginLinks, $links );
	}
}
