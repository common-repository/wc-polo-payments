<?php

namespace PoloPagPayments\Gateway;

use WC_Payment_Gateway;
use WC_Logger;
use PoloPagPayments\Polopag\PolopagApiV1;
use WC_Admin_Settings;

/**
 * Pix GeteWay class
 */
class PoloPixGateway extends WC_Payment_Gateway {
	public $api_version;

	public $debug;

	public $api;

	public $checkout_message;

	public $api_key;

	public $thank_you_message;

	public $order_recived_message;

	public $email_instruction;

	public $log;

	public $expiration_seconds;

	public $adicional_infos;

	public $show_instructions;
	public $show_pix_image;

	public $before_paid_status;

	public $pix_icon_size;

	public $pix_icon_color;

	public $after_paid_status;

	public $apply_discount_amount;

	public $auto_cancel;

	public $apply_discount;

	public $check_payment_interval;

	/**
	 * Discount type
	 *
	 * @var string $type The type of the gateway. Can be 'percentage' or 'fixed'.
	 */
	public $apply_discount_type;

	private static $instance;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		self::$instance = $this;

		global $current_section;

		$this->id = 'polopagpayments_geteway';
		$this->icon = false;
		$this->has_fields = true;
		$this->method_title = 'PoloPag Pix';
		$this->method_description = 'Pagamento via PIX processados pela PoloPag.';
		$this->supports = array( 'products' );

		// Method with all the options fields
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		//Main settings
		$this->setup_settings();

		// Set the API.
		if ( $this->api_version == 'v1' )
			$this->api = new PolopagApiV1( $this );

		// Active logs.
		if ( 'yes' === $this->debug ) {
			$this->log = new WC_Logger();
		}

		// Actions.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		add_action( 'woocommerce_order_details_before_order_table', array( $this, 'order_view_page' ) );
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
		add_action( 'woocommerce_api_' . $this->id, array( $this, 'ipn_handler' ) );
		add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'my_account_my_orders_actions' ), 10, 2 );
	}

	/**
	 *  Check if pix qr code expired and change actions buttons
	 *
	 * @since 1.0.0
	 * @param array $actions
	 * @param \WC_Order $order
	 * @return array
	 */
	public function my_account_my_orders_actions( $actions, $order ) {
		$payment_method = $order->get_payment_method();

		if ( $payment_method == $this->id &&
			$order->get_meta( '_polopagpayments_paid' ) != 'yes' && $order->get_status() != 'cancelled' ) {
			$expiration = $order->get_meta( '_polopagpayments_expiration_date' );
			$expiration_time = strtotime( $expiration );
			if ( $expiration_time > current_time( 'timestamp' ) ) {
				$actions['view']['name'] = __( 'Pagar', 'wc-polo-payments' );
				unset( $actions['pay'] );
			} else {
				unset( $actions['view'] );
			}
		}

		return $actions;
	}

	/**
	 * Update admin options
	 * 
	 * @since 1.1.0
	 * @return void
	 */
	public function process_admin_options() {
		$current_tab = $this->get_current_tab();
		$update_settings = get_option( $this->get_option_key(), [] );

		if ( ! is_array( $update_settings ) )
			$update_settings = [];

		switch ( $current_tab ) {
			case 'general':
				$title = filter_input( INPUT_POST, $this->get_field_name( 'title' ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$api_key = filter_input( INPUT_POST, $this->get_field_name( 'api_key' ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$api_version = filter_input( INPUT_POST, $this->get_field_name( 'api_version' ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$debug = filter_input( INPUT_POST, $this->get_field_name( 'debug' ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$before_paid_status = filter_input( INPUT_POST, $this->get_field_name( 'before_paid_status' ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$after_paid_status = filter_input( INPUT_POST, $this->get_field_name( 'after_paid_status' ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );

				if ( empty( $api_key ) || empty( $title ) || empty( $api_version ) ) {
					WC_Admin_Settings::add_error( __( 'É preciso preencher a todos os campos', 'wc-polo-payments' ) );
					return;
				}

				$update_settings['api_key'] = $api_key;
				$update_settings['api_version'] = $api_version;
				$update_settings['title'] = $title;
				$update_settings['debug'] = isset( $debug ) ? 'yes' : 'no';
				$update_settings['after_paid_status'] = $after_paid_status;
				$update_settings['before_paid_status'] = $before_paid_status;
				break;
			case 'customize':
				$checkout_message = wp_kses_post( filter_input( INPUT_POST, $this->get_field_name( 'checkout_message' ) ) );
				$order_recived_message = wp_kses_post( filter_input( INPUT_POST, $this->get_field_name( 'order_recived_message' ) ) );
				$thank_you_message = wp_kses_post( filter_input( INPUT_POST, $this->get_field_name( 'thank_you_message' ) ) );
				$pix_icon_color = filter_input( INPUT_POST, $this->get_field_name( 'pix_icon_color' ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$pix_icon_size = filter_input( INPUT_POST, $this->get_field_name( 'pix_icon_size' ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$show_pix_image = filter_input( INPUT_POST, $this->get_field_name( 'show_pix_image' ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );

				$update_settings['checkout_message'] = $checkout_message;
				$update_settings['order_recived_message'] = $order_recived_message;
				$update_settings['show_pix_image'] = isset( $show_pix_image ) ? 'yes' : 'no';
				$update_settings['thank_you_message'] = $thank_you_message;
				$update_settings['pix_icon_color'] = $pix_icon_color;
				$update_settings['pix_icon_size'] = $pix_icon_size;
				$update_settings['pix_icon'] = 'data:image/svg+xml;base64, ' . base64_encode( preg_replace( '/#32BCAD/i', $pix_icon_color, '<svg viewBox="0 0 47.999999 47.999999" version="1.1" width="' . $pix_icon_size . '" height="' . $pix_icon_size . '" xmlns="http://www.w3.org/2000/svg" xmlns:svg="http://www.w3.org/2000/svg"><path d="m 37.212736,36.519836 a 6.8957697,6.8957697 0 0 1 -4.906519,-2.025174 l -7.087361,-7.09185 a 1.3471224,1.3471224 0 0 0 -1.862022,0 l -7.11131,7.111308 a 6.8987632,6.8987632 0 0 1 -4.906518,2.031162 H 9.9514702 l 8.9808148,8.980816 a 7.1846526,7.1846526 0 0 0 10.149819,0 l 8.998777,-9.000275 z" fill="#32BCAD"/><path d="m 11.340503,11.457373 a 6.8972665,6.8972665 0 0 1 4.906518,2.03116 l 7.11131,7.112807 a 1.318683,1.318683 0 0 0 1.862022,0 l 7.085864,-7.085865 a 6.8852919,6.8852919 0 0 1 4.906519,-2.032657 h 0.853176 L 29.067136,2.4840405 a 7.1756718,7.1756718 0 0 0 -10.149819,0 L 9.9514702,11.457373 Z" fill="#32BCAD"/><path d="M 45.509513,18.927915 40.071628,13.49003 a 1.0477618,1.0477618 0 0 1 -0.386174,0.07783 h -2.472718 a 4.8825701,4.8825701 0 0 0 -3.43217,1.421959 l -7.085862,7.081373 a 3.4037292,3.4037292 0 0 1 -4.809227,0 l -7.112806,-7.10831 A 4.8825701,4.8825701 0 0 0 11.340503,13.539424 H 8.3049864 a 1.0657234,1.0657234 0 0 1 -0.3652196,-0.07334 l -5.4723103,5.461833 a 7.1846526,7.1846526 0 0 0 0,10.149818 l 5.4603358,5.460331 a 1.0253097,1.0253097 0 0 1 0.3652196,-0.07335 h 3.0474911 a 4.884067,4.884067 0 0 0 3.432168,-1.423458 l 7.111309,-7.11131 c 1.285754,-1.284256 3.526467,-1.284256 4.810724,0 l 7.085862,7.084367 a 4.8825701,4.8825701 0 0 0 3.43217,1.421962 h 2.472718 a 1.0327938,1.0327938 0 0 1 0.386174,0.07783 l 5.437885,-5.437885 a 7.1756718,7.1756718 0 0 0 0,-10.149818" fill="#32BCAD"/></svg>' ) );

				break;
			case 'email':
				$email_instruction = wp_kses_post( filter_input( INPUT_POST, $this->get_field_name( 'email_instruction' ) ) );
				$update_settings['email_instruction'] = $email_instruction;
				break;
			case 'advanced':
				$check_payment_interval = filter_input( INPUT_POST, $this->get_field_name( 'check_payment_interval' ), FILTER_SANITIZE_NUMBER_INT );
				$auto_cancel = filter_input( INPUT_POST, $this->get_field_name( 'auto_cancel' ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$adicional_infos = filter_input( INPUT_POST, $this->get_field_name( 'adicional_infos' ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$show_instructions = filter_input( INPUT_POST, $this->get_field_name( 'show_instructions' ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$apply_discount = filter_input( INPUT_POST, $this->get_field_name( 'apply_discount' ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$apply_discount_amount = filter_input( INPUT_POST, $this->get_field_name( 'apply_discount_amount' ), FILTER_SANITIZE_NUMBER_INT );
				$apply_discount_type = filter_input( INPUT_POST, $this->get_field_name( 'apply_discount_type' ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$expiration_seconds = filter_input( INPUT_POST, $this->get_field_name( 'expiration_seconds' ), FILTER_VALIDATE_INT );

				if ( ! ( $expiration_seconds >= 600 && $expiration_seconds <= 86400 ) ) {
					WC_Admin_Settings::add_error( __( 'Valores inválidos para expiração do QR Code, deve ser entre 600 e 86400 segundos', 'wc-polo-payments' ) );
					return;
				}

				if ( $check_payment_interval <= 4 ) {
					WC_Admin_Settings::add_error( __( 'O intervalo não pode ser menor que 5 segundos para evitar sobrecarga.', 'wc-polo-payments' ) );
					return;
				}

				if ( isset( $apply_discount ) && ( empty( $apply_discount_amount ) || empty( $apply_discount_type ) ) ) {
					WC_Admin_Settings::add_error( __( 'Ao ativar o desconto você precisa preencher os campos.', 'wc-polo-payments' ) );
					return;
				}

				if ( isset( $apply_discount ) && $apply_discount_amount == '0' ) {
					WC_Admin_Settings::add_error( __( 'O desconto não pode ser 0.', 'wc-polo-payments' ) );
					return;
				}

				if ( isset( $apply_discount ) && ! preg_match( '/^[0-9]+([\,][0-9]{1,2})?$/i', $apply_discount_amount ) ) {
					WC_Admin_Settings::add_error( __( 'O desconto só poder ter números inteiros ou então separado por "," (vírgula) com até 2 casas decimais: ex: 10 ou 5,80', 'wc-polo-payments' ) );
					return;
				}

				$apply_discount_amount = preg_replace( '/,/i', '.', $apply_discount_amount );


				$update_settings['check_payment_interval'] = $check_payment_interval;
				$update_settings['auto_cancel'] = isset( $auto_cancel ) ? 'yes' : 'no';
				$update_settings['apply_discount'] = isset( $apply_discount ) ? 'yes' : 'no';
				$update_settings['adicional_infos'] = isset( $adicional_infos ) ? 'yes' : 'no';
				$update_settings['show_instructions'] = isset( $show_instructions ) ? 'yes' : 'no';
				$update_settings['apply_discount_amount'] = $apply_discount_amount;
				$update_settings['apply_discount_type'] = $apply_discount_type;
				$update_settings['expiration_seconds'] = $expiration_seconds;

				break;
		}

		update_option( $this->get_option_key(), apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, $update_settings ), 'yes' );
		$this->init_settings();
		$this->setup_settings();
	}

	/**
	 * Setup settings form fields.
	 * 
	 * @since 1.1.0
	 * @return void
	 */
	public function init_form_fields() {
		$myAccountText = esc_html__( 'PoloPag > Minha Conta', 'wc-polo-payments' );
		$generateLink = sprintf( '<a href="https://polopag.com/">%s</a>', $myAccountText );
		/* translators: %s: url */
		$apiKeyInfo = sprintf( __( 'Insira a API Key. Caso você não saiba você pode obter em %s.', 'wc-polo-payments' ), $generateLink );


		$debugUrl = esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&log_file=' . esc_attr( $this->id ) . '-' . sanitize_file_name( wp_hash( $this->id ) ) . '.log' ) );
		$generateLink = sprintf( '<a href="%s">%s</a>', $debugUrl, __( 'System Status > Logs', 'wc-polo-payments' ) );
		/* translators: %s: url */
		$seeDebugText = sprintf( __( 'Veja os logs do plugin e mensagens de depuração em %s', 'wc-polo-payments' ), $generateLink );

		$this->form_fields = array(
			'title' => array(
				'title' => esc_html__( 'Titulo', 'wc-polo-payments' ),
				'type' => 'text',
				'description' => esc_html__( 'Esse titulo irá aparecer na opção de pagamento para o cliente', 'wc-polo-payments' ),
				'default' => '',
				'custom_attributes' => array(
					'required' => 'required',
				),
			),
			'api_version' => array(
				'title' => esc_html__( 'Versão API', 'wc-polo-payments' ),
				'type' => 'select',
				'description' => esc_html__( 'Insira a versão da API PoloPag', 'wc-polo-payments' ),
				'default' => 'v1',
				'options' => array( 'v1' => 'v1 (stable)' ),
				'custom_attributes' => array(
					'required' => 'required',
				),
			),
			'api_key' => array(
				'title' => esc_html__( 'API Key', 'wc-polo-payments' ),
				'type' => 'text',
				'description' => wp_kses( $apiKeyInfo, [ "a" => [ "href" => [] ] ] ),
				'default' => '',
				'custom_attributes' => array(
					'required' => 'required',
				),
			),
			'before_paid_status' => array(
				'title' => esc_html__( 'Status inicial do pedido:', 'wc-polo-payments' ),
				'type' => 'select',
				'description' => esc_html__( 'Defina o status inicial que o pedido ficará quando ele for criado (antes do pagamento).', 'wc-polo-payments' ),
				'default' => '',
				'options' => wc_get_order_statuses(),
				'custom_attributes' => array(
					'required' => 'required',
				),
			),
			'after_paid_status' => array(
				'title' => esc_html__( 'Após pagamento mudar status para:', 'wc-polo-payments' ),
				'type' => 'select',
				'description' => esc_html__( 'Defina o status que o pedido ficará após o pagamento ser confirmado.', 'wc-polo-payments' ),
				'default' => '',
				'options' => wc_get_order_statuses(),
				'custom_attributes' => array(
					'required' => 'required',
				),
			),
			'debug' => array(
				'title' => esc_html__( 'Debug Log', 'wc-polo-payments' ),
				'type' => 'checkbox',
				'label' => esc_html__( 'Ativar logs', 'wc-polo-payments' ),
				'default' => 'no',
				'description' => wp_kses( $seeDebugText, [ "a" => [ "href" => [] ] ] ),
			)
		);
	}

	/**
	 * Set settings function
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function setup_settings() {
		// Define user set variables.
		$this->enabled = $this->get_option( 'enabled' );
		$this->title = $this->get_option( 'title', 'Pix' );
		$this->description = $this->get_option( 'description' );
		$this->debug = $this->get_option( 'debug' );
		$this->api_version = $this->get_option( 'api_version', 'v1' );
		$this->api_key = $this->get_option( 'api_key' );
		$this->checkout_message = $this->get_option( 'checkout_message', "<p><strong>Ao finalizar a compra, iremos gerar o código Pix para pagamento.</strong><br><br>Nosso sistema detecta automaticamente o pagamento sem precisar enviar comprovantes.</p>" );
		$this->order_recived_message = $this->get_option( 'order_recived_message', '<h4 style="text-align: center;">Faça o pagamento para finalizar!</h4><p style="text-align: center;">Escaneie o código QR ou copie o código abaixo para fazer o PIX.<br>O sistema vai detectar automáticamente quando fizer a transferência.</p><p style="text-align: center;"><strong>Podemos demorar até 5 minutos para detectarmos o pagamento.</strong></p><p style="text-align: center;">[copy_button]</p><p style="text-align: center;">[qr_code]</p>' );
		$this->thank_you_message = $this->get_option( 'thank_you_message', '<p style="text-align: center;">Sua transferência PIX foi confirmada!<br>O seu pedido já está sendo separado e logo será enviado para seu endereço.</p>' );
		$this->email_instruction = $this->get_option( 'email_instruction', '<h4 style="text-align: center;">Faça o pagamento para finalizar a compra</h4><p style="text-align: center;">Escaneie o código abaixo</p><p style="text-align: center;">[qr_code]</p><h4 style="text-align: center;">ou</h4><p style="text-align: center;">[link text="Clique aqui"] para ver o código ou copiar</p>' );
		$this->pix_icon_color = $this->get_option( 'pix_icon_color', '#32BCAD' );
		$this->pix_icon_size = $this->get_option( 'pix_icon_size', '48' );
		$this->icon = apply_filters( 'woocommerce_gateway_icon', \POLOPAGPAYMENTS_PLUGIN_URL . 'assets/images/pix-payment-icon-32x32.svg', 'polopagpayments' );
		$this->expiration_seconds = (int) $this->get_option( 'expiration_seconds', 3600 );
		$this->before_paid_status = $this->get_option( 'before_paid_status', 'wc-pending' );
		$this->after_paid_status = $this->get_option( 'after_paid_status', 'wc-processing' );
		$this->check_payment_interval = $this->get_option( 'check_payment_interval', '5' );
		$this->auto_cancel = $this->get_option( 'auto_cancel', 'no' );
		$this->apply_discount = $this->get_option( 'apply_discount', 'no' );
		$this->apply_discount_type = $this->get_option( 'apply_discount_type', 'fixed' );
		$this->apply_discount_amount = $this->get_option( 'apply_discount_amount', '0' );
		$this->adicional_infos = $this->get_option( 'adicional_infos', 'yes' );
		$this->show_instructions = $this->get_option( 'show_instructions', 'yes' );
		$this->show_pix_image = $this->get_option( 'show_pix_image', 'yes' );
	}

	/**
	 * Get name of fields
	 * 
	 * @since 1.1.0
	 * @return string
	 */
	protected function get_field_name( string $field = '' ) {
		return 'woocommerce_' . $this->id . '_' . $field;
	}

	/**
	 * Get current tab
	 * 
	 * @since 1.1.0
	 * @return string
	 */
	protected function get_current_tab() {
		$current_tab = filter_input( INPUT_GET, 'mgn_tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$current_tab = isset( $current_tab ) ? $current_tab : 'general';

		return $current_tab;
	}

	/**
	 * Get current tab name
	 * 
	 * @since 1.1.0
	 * @return string
	 */
	protected function get_current_tab_name() {
		$tabs = $this->get_tabs();

		return $tabs[ $this->get_current_tab()];
	}

	/**
	 * Tabs
	 * 
	 * @since 1.1.0
	 * @return array
	 */
	protected function get_tabs() {
		return [ 
			'general' => __( 'Geral', 'wc-polo-payments' ),
			'customize' => __( 'Customizar', 'wc-polo-payments' ),
			'email' => __( 'E-mail', 'wc-polo-payments' ),
			'advanced' => __( 'Avançado', 'wc-polo-payments' )
		];
	}

	/**
	 * Admin page settings.
	 */
	public function admin_options() {
		$baseUrl = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $this->id );

		$current_tab = $this->get_current_tab();
		$current_tab_name = $this->get_current_tab_name();

		$tab_template = \POLOPAGPAYMENTS_PLUGIN_PATH . 'templates/admin/settings/' . $current_tab . '-settings.php';

		require_once( \POLOPAGPAYMENTS_PLUGIN_PATH . 'templates/admin/settings/header-settings.php' );

		if ( file_exists( $tab_template ) )
			require_once( $tab_template );
	}

	/**
	 * Payment fields.
	 */
	public function payment_fields() {
		if ( $description = $this->get_description() ) {
			echo wp_kses_post( wpautop( wptexturize( $description ) ) );
		}

		$show_pix_image = $this->show_pix_image == 'yes' ? true : false;

		wc_get_template(
			'html-woocommerce-instructions.php',
			[ 
				'description' => $this->get_description(),
				'checkout_message' => $this->checkout_message,
				'show_pix_image' => $show_pix_image
			],
			WC()->template_path() . \POLOPAGPAYMENTS_DIR_NAME . '/',
			POLOPAGPAYMENTS_PLUGIN_PATH . 'templates/'
		);
	}

	/**
	 * Process the payment.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return array Redirect data.
	 */
	public function process_payment( $order_id ) {
		return $this->api->process_regular_payment( $order_id );
	}

	/**
	 * Thank You page message.
	 *
	 * @param int $order_id Order ID.
	 */
	public function thankyou_page( $order_id ) {
		$order = wc_get_order( $order_id );
		$qr_code = $order->get_meta( '_polopagpayments_qr_code' );
		$expiration_date = $order->get_meta( '_polopagpayments_expiration_date' );
		$qr_code_image = $order->get_meta( '_polopagpayments_qr_code_image' );

		wc_get_template(
			'html-woocommerce-thank-you-page.php',
			[ 
				'qr_code' => $qr_code,
				'thank_you_message' => $this->thank_you_message,
				'order_recived_message' => $this->order_recived_message,
				'order' => $order,
				'qr_code_image' => $qr_code_image,
				'order_key' => $order->get_order_key(),
				'expiration_date' => $expiration_date,
				'show_instructions' => ( ( isset( $this->show_instructions ) && $this->show_instructions === 'yes' ) || ! isset( $this->show_instructions ) ) ? true : false

			],
			WC()->template_path() . \POLOPAGPAYMENTS_DIR_NAME . '/',
			POLOPAGPAYMENTS_PLUGIN_PATH . 'templates/'
		);
	}

	/**
	 * Pix QR Code in Order View
	 * 
	 * @since 1.1.0
	 * @return void
	 */
	public function order_view_page( $order ) {
		if (
			$order->get_status() == 'on-hold' &&
			$order->get_payment_method() == $this->id &&
			is_wc_endpoint_url( 'view-order' )
		) {
			do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() );
		}
	}

	/**
	 * Add content to the WC emails.
	 *
	 * @param  object $order         Order object.
	 * @param  bool   $sent_to_admin Send to admin.
	 * @param  bool   $plain_text    Plain text or HTML.
	 *
	 * @return void                	Payment instructions.
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		if (
			! in_array( $order->get_status(), array( 'on-hold' ), true ) ||
			$this->id !== $order->payment_method
		) {
			return;
		}

		$email_type = $plain_text ? 'plain' : 'html';

		$qr_code = $order->get_meta( '_polopagpayments_qr_code' );
		$expiration_date = $order->get_meta( '_polopagpayments_expiration_date' );
		$qr_code_image = $order->get_meta( '_polopagpayments_qr_code_image' );

		wc_get_template(
			'email-new-order-instructions.php',
			[ 
				'qr_code' => $qr_code,
				'qr_code_image' => $qr_code_image,
				'email_instruction' => $this->email_instruction,
				'order_id' => $order->get_id(),
				'order_key' => $order->get_order_key(),
				'expiration_date' => $expiration_date,
				'order_url' => $order->get_checkout_order_received_url()
			],
			WC()->template_path() . \POLOPAGPAYMENTS_DIR_NAME . '/',
			POLOPAGPAYMENTS_PLUGIN_PATH . 'templates/emails/'
		);
	}

	/**
	 * Is Debug function
	 */
	public function is_debug() {
		return 'yes' === $this->debug ? true : false;
	}

	/**
	 * IPN handler.
	 */
	public function ipn_handler() {
		$this->api->ipn_handler();
	}

	public static function getInstance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}
