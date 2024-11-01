<?php
namespace PoloPagPayments;

use PoloPagPayments\WP\Helper as WP;
use PoloPagPayments\Gateway\BaseGateway;
use PoloPagPayments\Gateway\PoloPixGatewayBlocksSupport;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

//Prevent direct file call
defined( 'ABSPATH' ) || exit;

/**
 * WC Polopag Pix Payment
 *
 * @package PoloPagPayments
 * @since   1.0.0
 * @version 1.0.0
 */
class Core {
	/**
	 * The unique identifier of this plugin.
	 *
	 * @since 1.0.0
	 * @var string $pluginName
	 */
	public $pluginName;

	/**
	 * The current version of the plugin.
	 *
	 * @since 1.1.0
	 * @var string $pluginVersion
	 */
	public $pluginVersion;

	/**
	 * Path to plugin directory.
	 * 
	 * @since 1.1.0
	 * @var string $pluginPath Without trailing slash.
	 */
	public $pluginPath;

	/**
	 * URL to plugin directory.
	 * 
	 * @since 1.1.0
	 * @var string $pluginUrl Without trailing slash.
	 */
	public $pluginUrl;

	/**
	 * URL to plugin assets directory.
	 * 
	 * @since 1.1.0
	 * @var string $assetsUrl Without trailing slash.
	 */
	public $assetsUrl;

	/**
	 * Plugin settings.
	 * 
	 * @since 1.1.0
	 * @var array
	 */
	protected $settings;

	/**
	 * Startup plugin.
	 * 
	 * @since 1.1.0
	 * @return void
	 */

	/**
	 * Initialize the plugin public actions.
	 */
	public function __construct() {
		$this->pluginUrl = \POLOPAGPAYMENTS_PLUGIN_URL;
		$this->pluginPath = \POLOPAGPAYMENTS_PLUGIN_PATH;
		$this->assetsUrl = $this->pluginUrl . '/assets';

		$this->pluginName = \POLOPAGPAYMENTS_PLUGIN_NAME;
		$this->pluginVersion = \POLOPAGPAYMENTS_PLUGIN_VERSION;

		WP::add_action( 'plugins_loaded', $this, 'after_load' );
		WP::add_filter( 'cron_schedules', $this, 'add_two_minutes' );
	}

	public function add_two_minutes( $schedules ) {
		$schedules['2min'] = array(
			'interval' => 2 * 60,
			'display' => 'Once every 2 minutes',
		);
		return $schedules;
	}

	/**
	 * Plugin loaded method.
	 * 
	 * @since 1.1.0
	 * @return void
	 */
	public function after_load() {
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			// Cannot start plugin
			return;
		}

		// Startup gateway
		BaseGateway::init();

		WP::add_action( 'wp_enqueue_scripts', $this, 'enqueue_scripts' );
		WP::add_action( 'admin_enqueue_scripts', $this, 'admin_enqueue_scripts' );
		WP::add_action( 'wp_enqueue_scripts', $this, 'front_enqueue_scripts' );
		WP::add_action( 'woocommerce_view_order', $this, 'woocommerce_view_order_page', 2 );
		WP::add_action( 'before_woocommerce_init', $this, 'woocommerce_declare_compatibility' );
		WP::add_action( 'woocommerce_blocks_loaded', $this, 'woocommerce_gateway_woocommerce_block_support' );
	}

	public function woocommerce_declare_compatibility() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', \POLOPAGPAYMENTS_FILE_NAME, true );
		}
	}

	public function is_pix_payment_page() {
		global $wp;

		//Page is view order or order received?
		if ( ! isset( $wp->query_vars['order-received'] ) && ! isset( $wp->query_vars['view-order'] ) )
			return false;

		$query_var = isset( $wp->query_vars['order-received'] ) ? $wp->query_vars['order-received'] : $wp->query_vars['view-order'];

		$order_id = absint( $query_var );

		if ( empty( $order_id ) || $order_id == 0 )
			return false;

		$order = wc_get_order( $order_id );

		if ( ! $order )
			return false;

		if (
			( ( is_wc_endpoint_url( 'order-received' ) && is_checkout() ) ||
				is_wc_endpoint_url( 'view-order' ) ) &&
			'polopagpayments_geteway' == $order->get_payment_method()
		)
			return true;

		return false;
	}

	public function enqueue_scripts() {
		if ( $this->is_pix_payment_page() ) {
			$interval = 5;
			$plugin_options = maybe_unserialize( get_option( 'woocommerce_polopagpayments_geteway_settings', false ) );

			if ( $plugin_options && isset( $plugin_options['check_payment_interval'] ) )
				$interval = $plugin_options['check_payment_interval'];

			$options = [ 
				'checkInterval' => intval( $interval * 1000 ),
				'nonce' => wp_create_nonce( 'check_pix_nonce' ),
			];

			wp_enqueue_script( \POLOPAGPAYMENTS_PLUGIN_NAME . '-checkout', \POLOPAGPAYMENTS_PLUGIN_URL . 'assets/js/public/checkout.js', array( 'jquery' ), \POLOPAGPAYMENTS_PLUGIN_VERSION, false );
			wp_add_inline_script( \POLOPAGPAYMENTS_PLUGIN_NAME . '-checkout', sprintf( 'const polopagpayments_geteway = %s;', wp_json_encode( $options ) ), 'before' );
		}

		if ( is_checkout() ) {
			wp_enqueue_script( \POLOPAGPAYMENTS_PLUGIN_NAME . 'before-checkout', \POLOPAGPAYMENTS_PLUGIN_URL . 'assets/js/public/before-checkout.js', array( 'jquery' ), \POLOPAGPAYMENTS_PLUGIN_VERSION, false );
		}
	}


	function woocommerce_view_order_page( $order_id ) {
		$plugin_options = maybe_unserialize( get_option( 'woocommerce_polopagpayments_geteway_settings', false ) );

		$order = wc_get_order( $order_id );
		$qr_code = $order->get_meta( '_polopagpayments_qr_code' );
		$qr_code_image = $order->get_meta( '_polopagpayments_qr_code_image' );
		$expiration_date = $order->get_meta( '_polopagpayments_expiration_date' );
		$status = $order->get_status();
		$payment_method = $order->get_payment_method();

		if ( $payment_method != 'polopagpayments_geteway' || $status != 'pending' )
			return;

		wc_get_template(
			'html-woocommerce-thank-you-page.php',
			[ 
				'qr_code' => $qr_code,
				'thank_you_message' => '',
				'order_recived_message' => '',
				'order' => $order,
				'qr_code_image' => $qr_code_image,
				'order_key' => $order->get_order_key(),
				'expiration_date' => $expiration_date,
				'show_instructions' => ( ( isset( $plugin_options['show_instructions'] ) && $plugin_options['show_instructions'] === 'yes' ) || ! isset( $plugin_options['show_instructions'] ) ) ? true : false

			],
			WC()->template_path() . \POLOPAGPAYMENTS_DIR_NAME . '/',
			POLOPAGPAYMENTS_PLUGIN_PATH . 'templates/'
		);
	}

	/**
	 * Enqueue public scripts and styles.
	 * 
	 * @since 1.0.0
	 */
	public function front_enqueue_scripts() {
		wp_enqueue_style( \POLOPAGPAYMENTS_PLUGIN_NAME . '-styles',
			\POLOPAGPAYMENTS_PLUGIN_URL . 'assets/css/public/styles.css',
			array(),
			\POLOPAGPAYMENTS_PLUGIN_VERSION,
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 * 
	 * @since 1.0.0
	 */
	public function admin_enqueue_scripts( $hook ) {
		// checking only within the admin if a query exists, no nonce is needed for this
		// phpcs:ignore
		if ( $hook != 'woocommerce_page_wc-settings' || ! ( isset( $_GET['section'] ) && $_GET['section'] == 'polopagpayments_geteway' ) )
			return;

		wp_enqueue_script(
			'colpick',
			\POLOPAGPAYMENTS_PLUGIN_URL . 'assets/js/admin/colpick/colpick.js',
			array( 'jquery' ),
			\POLOPAGPAYMENTS_PLUGIN_VERSION,
			false
		);

		wp_enqueue_script(
			\POLOPAGPAYMENTS_PLUGIN_NAME . '-settings',
			\POLOPAGPAYMENTS_PLUGIN_URL . 'assets/js/admin/settings.js',
			array( 'jquery' ),
			\POLOPAGPAYMENTS_PLUGIN_VERSION,
			false
		);

		wp_enqueue_style(
			'colpick',
			\POLOPAGPAYMENTS_PLUGIN_URL . 'assets/js/admin/colpick/colpick.css',
			array(),
			\POLOPAGPAYMENTS_PLUGIN_VERSION
		);

		wp_enqueue_style( \POLOPAGPAYMENTS_PLUGIN_NAME . '-styles',
			\POLOPAGPAYMENTS_PLUGIN_URL . 'assets/css/admin/styles.css',
			array(),
			\POLOPAGPAYMENTS_PLUGIN_VERSION,
		);
	}

	/**
	 * Registers WooCommerce Blocks integration.
	 *
	 */
	public static function woocommerce_gateway_woocommerce_block_support() {
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			require_once \POLOPAGPAYMENTS_PLUGIN_PATH . 'src/Gateway/PoloPixGatewayBlocksSupport.php';
			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function (PaymentMethodRegistry $payment_method_registry) {
					$payment_method_registry->register( new PoloPixGatewayBlocksSupport() );
				}
			);
		}
	}
}
