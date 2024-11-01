<?php

namespace PoloPagPayments\Polopag;

abstract class PolopagApi {

	/**
	 * API URL.
	 *
	 * @var string
	 */
	protected $api_url;

	/**
	 * Gateway class.
	 *
	 * @var \PoloPagPayments\Gateway\PoloPixGateway;
	 */
	protected $gateway;

	/**
	 * Endpoint url.
	 *
	 * @var string
	 */
	protected $endpoint;

	/**
	 * Request Header Parameters.
	 *
	 * @var string
	 */
	protected $headers = array();

	/**
	 * Get API URL.
	 *
	 * @return string
	 */
	public function get_api_url() {
		return $this->api_url;
	}

	/**
	 * Constructor.
	 *
	 * @param \WC_Payment_Gateway $gateway Gateway instance.
	 */
	public function __construct( $gateway = null ) {
		$this->gateway = $gateway;
	}

	/**
	 * Do requests in the API.
	 *
	 * @param  string $endpoint API Endpoint.
	 * @param  string $method   Request method.
	 * @param  array  $data     Request data.
	 * @param  array  $headers  Request headers.
	 *
	 * @return array            Request response.
	 */
	protected function do_request( $endpoint, $method = 'POST', $data = array(), $headers = array() ) {
		$params = array(
			'method' => $method,
			'timeout' => 60,
		);

		if ( ! empty( $data ) ) {
			$params['body'] = wp_json_encode( $data );
		}

		// User-agent and api version.
		$x_polopag_useragent = 'wc-polo-payments/' . POLOPAGPAYMENTS_PLUGIN_VERSION;

		if ( defined( 'WC_VERSION' ) ) {
			$x_polopag_useragent .= ' woocommerce/' . WC_VERSION;
		}

		$x_polopag_useragent .= ' wordpress/' . get_bloginfo( 'version' );
		$x_polopag_useragent .= ' php/' . phpversion();

		$params['headers'] = [ 
			'User-Agent' => $x_polopag_useragent,
		];

		$params['headers'] = array_merge( $params['headers'], $this->headers, $headers );

		if ( $this->gateway->is_debug() ) {
			$this->gateway->log->add( $this->gateway->id, sprintf( "Send Safe Post Request to: %s%s", $this->get_api_url(), $endpoint ) );
		}

		return wp_safe_remote_post( $this->get_api_url() . $endpoint, $params );
	}

	/**
	 * Do the transaction.
	 *
	 * @param  \WC_Order $order Order data.
	 * @param  array    $args  Transaction args.
	 * @param  string   $token Checkout token.
	 *
	 * @return array           Response data.
	 */
	public function do_transaction( $order, $args, $token = '' ) {
		if ( 'yes' === $this->gateway->debug ) {
			$this->gateway->log->add( $this->gateway->id, sprintf( 'Doing a transaction for order %s', $order->get_order_number() ) );
		}

		$response = $this->do_request( $this->endpoint, 'POST', $args );

		if ( $this->gateway->is_debug() ) {
			$this->gateway->log->add( $this->gateway->id, "Recived response..." );
		}

		if ( is_wp_error( $response ) ) {
			if ( 'yes' === $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, sprintf( 'WP_Error in doing the transaction: %s', esc_html( $response->get_error_message() ) ) );
			}

			return array();
		} else {
			$raw = json_decode( $response['body'], true );

			$data_sanitized = [ 
				"txid" => sanitize_text_field( $raw['txid'] ),
				"pixCopiaECola" => sanitize_text_field( $raw['pixCopiaECola'] ),
				"internalId" => sanitize_text_field( $raw['internalId'] ),
				"qrcodeBase64" => sanitize_text_field( $raw['qrcodeBase64'] ),
			];

			if ( isset( $raw['error'] ) ) {
				$data_sanitized['error'] = sanitize_text_field( $raw['error'] );
			}

			return $data_sanitized;
		}
	}

	/**
	 * Generate the transaction data.
	 *
	 * @param  \WC_Order $order  Order data.
	 *
	 * @return array            Transaction data.
	 */
	abstract public function generate_transaction_data( $order );

	/**
	 * Process regular payment.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return array Redirect data.
	 */
	abstract public function process_regular_payment( $order_id );

	/**
	 * Check if response is validity.
	 *
	 * @param  array $ipn_response IPN response data.
	 *
	 * @return bool
	 */
	abstract public function check_fingerprint( $ipn_response );

	/**
	 * IPN handler.
	 */
	abstract public function ipn_handler();

	/**
	 * Process successeful IPN requests.
	 *
	 * @param array $posted Posted data.
	 */
	abstract public function process_successful_ipn( $posted );

	/**
	 * Process the order status.
	 *
	 * @param \WC_Order $order  Order data.
	 * @param string   $status Transaction status.
	 */
	public function process_order_status( $order, $status ) {
		if ( $this->gateway->is_debug() ) {
			$this->gateway->log->add( $this->gateway->id, sprintf( 'PIX: Payment status for order %s is now: %s', $order->get_order_number(), $status ) );
		}

		switch ( $status ) {
			case 'AGUARDANDO':
				$statuses = wc_get_order_statuses();
				/* translators: %s: status */
				$order->update_status( $this->gateway->before_paid_status, sprintf( esc_html__( 'PoloPag PIX: Status inicial alterado para %s.', 'wc-polo-payments' ), esc_html( $statuses[ $this->gateway->before_paid_status ] ) ) );
				break;
			case 'APROVADO':
				if ( ! in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ) {
					$order->add_order_note( __( 'Polopag PIX: Transação paga.', 'wc-polo-payments' ) );
				}

				if ( $this->gateway->is_debug() ) {
					$this->gateway->log->add( $this->gateway->id, sprintf( 'UPDATING: order id %s to yes', $order->get_id() ) );
				}

				$order->update_meta_data( '_polopagpayments_paid', 'yes' );
				$order->save();

				// Changing the order for processing and reduces the stock.
				$order->payment_complete();

				$after_paid_status = $this->gateway->after_paid_status;


				$statuses = wc_get_order_statuses();
				/* translators: %s: status */
				$order->update_status( $after_paid_status, sprintf( esc_html__( 'PoloPag PIX: Pedido alterado para %s.', 'wc-polo-payments' ), esc_html( $statuses[ $after_paid_status ] ) ) );

				break;
			default:
				break;
		}
	}

	/**
	 * Only numbers.
	 *
	 * @param  string|int $string String to convert.
	 *
	 * @return string|int
	 */
	protected function only_numbers( $string ) {
		return preg_replace( '([^0-9])', '', $string );
	}
}
