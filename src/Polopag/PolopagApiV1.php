<?php

namespace PoloPagPayments\Polopag;

use WP_Filesystem_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once( ABSPATH . 'wp-admin/includes/file.php' );

class PolopagApiV1 extends PolopagApi {
	protected $api_url = 'https://api.polopag.com/v1/';

	protected $endpoint = 'cobpix';

	public function __construct( $gateway = null ) {
		$this->gateway = $gateway;

		$this->headers = [ 
			'Api-Key' => $gateway->api_key,
			'Content-Type' => 'application/json',
			'Accept' => 'application/json'
		];
	}

	/**
	 * Generate the transaction data.
	 *
	 * @param  \WC_Order $order  Order data.
	 *
	 * @return array | null           Transaction data.
	 */
	public function generate_transaction_data( $order ) {
		// Set the request data.
		$data = array(
			'valor' => number_format( $order->get_total(), 2, ".", "" ),
			'calendario' => [ 
				'expiracao' => $this->gateway->expiration_seconds,
			],
			'referencia' => wp_create_nonce( 'process_payment' ),
			'solicitacaoPagador' => get_bloginfo( 'name' ) . ' - ' . 'Pedido ' . $order->get_id(),
			'webhookUrl' => WC()->api_request_url( $this->gateway->id )
		);


		if ( isset( $order->billing_persontype ) && $order->billing_persontype == '2' && ! empty( $order->billing_cnpj ) ) {
			$data['devedor']['cnpj'] = preg_replace( '/[^0-9]/', '', $order->billing_cnpj );
			$data['devedor']['nome'] = $order->billing_company;

		} else {
			$data['devedor']['cpf'] = empty( $order->billing_cpf ) ? '' : preg_replace( '/[^0-9]/', '', $order->billing_cpf );
			$data['devedor']['nome'] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
		}

		if ( $this->gateway->adicional_infos == 'yes' ) {
			$items = $order->get_items();
			foreach ( $items as $item ) {
				$itemAdd["nome"] = utf8_uri_encode( sprintf( '%s x %d', substr( $item->get_name(), 0, 40 ), $item->get_quantity() ) );
				$itemAdd["valor"] = number_format( $item->get_total(), 2, ".", "," );
				$data["infoAdicionais"][] = $itemAdd;
			}
		}

		// Add filter for Third Party plugins.
		return apply_filters( 'polopagpayments_transaction_data', $data, $order );
	}

	public function process_regular_payment( $order_id ) {
		$order = wc_get_order( $order_id );


		WP_Filesystem();
		/**
		 * File System Base
		 * 
		 * @var WP_Filesystem_Base
		 */
		global $wp_filesystem;


		if ( $this->gateway->is_debug() ) {
			$this->gateway->log->add( $this->gateway->id, 'API PolopagPix: Init process payment' );
		}

		$data = $this->generate_transaction_data( $order );

		if ( $data == null ) {
			return array(
				'result' => 'fail',
			);
		}

		$transaction = $this->do_transaction( $order, $data );

		if ( isset( $transaction['error'] ) ) {
			wc_add_notice( esc_html( sprintf( 'PoloPag: %s', $transaction['error'] ) ), 'error' );
			return array(
				'result' => 'fail',
			);
		} else {
			$upload = wp_upload_dir();
			$upload_folder = sprintf( '%s/%s/qr-codes/', $upload['basedir'], \POLOPAGPAYMENTS_DIR_NAME );
			$upload_url = sprintf( '%s/%s/qr-codes/', $upload['baseurl'], \POLOPAGPAYMENTS_DIR_NAME );

			if ( ! file_exists( $upload_folder ) ) {
				wp_mkdir_p( $upload_folder );
			}
			$qrcode_file_name = gmdate( 'Ymd', strtotime( current_time( 'mysql' ) ) ) . $transaction['txid'] . '.png';

			$qrcode_data = base64_decode( $transaction['qrcodeBase64'] );
			$file_path = $upload_folder . $qrcode_file_name;

			$wp_filesystem->put_contents( $file_path, $qrcode_data );

			if ( 'yes' === $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'Saving qrcode image: [OK]' );
			}

			$order->update_meta_data( '_polopagpayments_qr_code_image', $upload_url . $qrcode_file_name );
			$order->update_meta_data( '_polopagpayments_qr_code', $transaction['pixCopiaECola'] );
			$order->update_meta_data( '_polopagpayments_expiration_date', gmdate( 'Y-m-d H:i:s', strtotime( '+' . $this->gateway->expiration_seconds . ' seconds', current_time( 'timestamp' ) ) ) );
			$order->update_meta_data( '_polopagpayments_transaction_id', $transaction['txid'] );
			$order->update_meta_data( '_polopagpayments_internal_id', $transaction['internalId'] );
			$order->update_meta_data( '_polopagpayments_paid', 'no' );

			$order->save();


			if ( 'yes' === $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'Saving new order: [OK]' );
			}

			$this->process_order_status( $order, 'AGUARDANDO' );

			// Empty the cart.
			WC()->cart->empty_cart();

			if ( 'yes' === $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'Cleaning the cart: [OK]' );
			}

			$order->add_order_note( sprintf( __( 'Polopag PIX: InternalID: %s', 'wc-polo-payments' ), $transaction['internalId'] ) );
			$order->add_order_note( sprintf( __( 'Polopag PIX: Copia e cola: %s', 'wc-polo-payments' ), $transaction['pixCopiaECola'] ) );

			// Redirect to thanks page.
			return array(
				'result' => 'success',
				'redirect' => $this->gateway->get_return_url( $order ),
			);
		}
	}

	public function check_fingerprint( $ipn_response ) {
		if ( isset( $ipn_response['txid'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * process data
	 *
	 * @param array $data
	 * @return void
	 */
	public function process_successful_ipn( $data ) {


		$args = array(
			'limit' => 1,
			// phpcs:ignore only way to search for orders by meta key
			'meta_key' => '_polopagpayments_internal_id',
			// phpcs:ignore only way to search for orders by meta_value
			'meta_value' => $data['internalId'],
			'meta_compare' => '=',
		);

		$orders = wc_get_orders( $args );

		if ( empty( $orders ) ) {
			$this->gateway->log->add( $this->gateway->id, sprintf( 'ERRO: Nenhum internalId = %s', $data['internalId'] ) );
			return;
		}

		$order = reset( $orders );

		if ( ! $orders ) {
			$this->gateway->log->add( $this->gateway->id, sprintf( 'ERRO: Nenhum internalId = %s', $data['internalId'] ) );
			return;
		}

		if ( $this->gateway->is_debug() ) {
			$this->gateway->log->add( $this->gateway->id, sprintf( 'Sucesso: taxid = %s', $data['txid'] ) );
			$this->gateway->log->add( $this->gateway->id, sprintf( 'Sucesso: internalId = %s', $data['internalId'] ) );
			$this->gateway->log->add( $this->gateway->id, sprintf( 'Sucesso: OrderID = %s', $order->get_id() ) );
		}

		$this->process_order_status( $order, $data['status'] );

	}

	public function ipn_handler() {
		@ob_clean();

		$data = json_decode( file_get_contents( 'php://input' ), true );

		if ( $this->gateway->is_debug() ) {
			$this->gateway->log->add( $this->gateway->id, '[INFO] Recived webhook response.' );
		}

		if ( empty( $data ) ) {
			if ( $this->gateway->is_debug() ) {
				$this->gateway->log->add( $this->gateway->id, '[WARN] Empty webhook data.' );
			}
			wp_send_json_error( esc_html__( 'Empty webhook data', 'wc-polo-payments' ), 400 );
			return;
		}

		// Check Nonce
		if ( ! isset( $data['referencia'] ) ) {
			wp_send_json_error( esc_html__( 'Request refused.', 'wc-polo-payments' ), 403 );
			return;
		}

		if ( empty( $data['internalId'] ) || empty( $data['txid'] ) || empty( $data['status'] ) ) {
			if ( $this->gateway->is_debug() ) {
				$this->gateway->log->add( $this->gateway->id, '[WARN] Empty required fields.' );
			}
			wp_send_json_error( esc_html__( 'Empty required fields', 'wc-polo-payments' ), 400 );
			return;
		}

		$internalId = sanitize_text_field( wp_unslash( $data['internalId'] ) );
		$txid = sanitize_text_field( wp_unslash( $data['txid'] ) );
		$status = sanitize_text_field( wp_unslash( $data['status'] ) );


		if ( $this->gateway->is_debug() ) {
			$this->gateway->log->add( $this->gateway->id, 'Retornou um POSTBACK' );
			$this->gateway->log->add( $this->gateway->id, sprintf( 'internalId: %s, txid: %s, status %s', $internalId, $txid, $status ) );
		}

		$data = [ 
			"internalId" => $internalId,
			"txid" => $txid,
			"status" => $status
		];

		if ( $data && $this->check_fingerprint( $data ) ) {
			header( 'HTTP/1.1 200 OK' );

			$this->process_successful_ipn( $data );

			wp_send_json(
				array(
					'success' => true,
				),
				200
			);
			exit;
		} else {

			wp_die( esc_html__( 'PoloPag PIX Requisição Falhou', 'wc-polo-payments' ), '', array( 'response' => 401 ) );
		}
	}
}
