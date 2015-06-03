<?php
/**
 * Plugin Name: WooCommerce Worldpay Gateway - Worldpay Online Payments
 * Plugin URI:
 * Description: A plugin for integrating the worldpay payment gateway with Woo Commerce. Supports GBP, EUR and USD.
 * Version: 1.0
 * Author: Worldpay
 * Author URI:
 * Requires at least: 4.0
 * Tested up to: 4.1
 *
 *
 * @package WooCommerce Worldpay Gateway
 * @category Core
 * @author Worldpay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	require_once('libs/worldpay-lib-php-1.6/lib/worldpay.php');
	require_once('Persistence/token.php');
	require_once('Persistence/card-details.php');
	require_once('Forms/payment-form.php');
	require_once('Forms/admin-form.php');
	require_once('Webhooks/webhook-request.php');
	require_once('Constants/worldpay-response-states.php');

	function init_woocommerce_worldpay_payment_gateway() {
		class WC_Gateway_Worldpay extends WC_Payment_Gateway {
			protected $client_key;
			protected $store_tokens;
			protected $is_test;
			protected $notifications_enabled;
			protected $service_key;
			private $worldpay_client;
			protected $supported_currencies = array('GBP','EUR','USD');

			public function __construct()
			{
				$this->id = "WC_Gateway_Worldpay";
				$this->has_fields = true;
				$this->method_title = "Worldpay";
				$this->method_description = "The Worldpay payment gateway";

				$this->supports = array(
					'products',
					'refunds'
				);

				$this->init_form_fields();
				$this->init_settings();
				$this->init_keys();
				if( ! in_array(get_woocommerce_currency(), $this->supported_currencies)
					|| empty($this->client_key)
					|| empty($this->service_key)
				) {
					$this->enabled = false;
				}
				$this->title = $this->get_option( 'title' );
				$this->store_tokens = $this->get_option( 'store_tokens' ) != "no";
				$this->notifications_enabled = $this->get_option( 'notifications_enabled' ) != "no";

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

				if ( is_checkout() ) {
					$this->enqueue_checkout_scripts();
				}

				add_action( 'woocommerce_api_wc_gateway_worldpay', array( $this, 'handle_webhook' ) );
			}

			protected function init_keys()
			{
				$this->is_test = $this->get_option('is_test') != "no";
				$this->client_key = $this->is_test
					? $this->get_option('test_client_key')
					: $this->get_option('client_key');
				$this->service_key = $this->is_test
					? $this->get_option('test_service_key')
					: $this->get_option('service_key');
			}

			public function init_form_fields()
			{
				$this->form_fields = Worldpay_AdminForm::get_admin_form_fields();
			}

			public function payment_fields()
			{
				Worldpay_PaymentForm::render_payment_form(
					$this->store_tokens,
					$this->get_stored_card_details()
				);
			}

			private function get_stored_card_details()
			{
				if( ! $this->store_tokens ){
					return null;
				}

				$currentUser = wp_get_current_user();
				return Worldpay_CardDetails::get_by_user($currentUser, $this->get_worldpay_client());
			}

			private function get_stored_token()
			{
				if( ! $this->store_tokens ){
					return null;
				}

				$current_user = wp_get_current_user();
				return Worldpay_Token::get_by_user($current_user);
			}

			public function process_payment( $order_id )
			{
				$currentUser = wp_get_current_user();
				$order = new WC_Order( $order_id );

				if ( $_POST['worldpay_use_saved_card_details'] ) {
					$token = $this->get_stored_token();
				} else {
					$token = $_POST['worldpay_token'];
				}

				$billing_address = array(
					"address1"=> $order->billing_address_1,
					"address2"=> $order->billing_address_2,
					"address3"=> '',
					"postalCode"=> $order->billing_postcode,
					"city"=> $order->billing_city,
					"state"=> $order->billing_state,
					"countryCode"=> $order->billing_country
				);

				try {
					$response = $this->get_worldpay_client()->createOrder(array(
						'token' => $token,
						'orderDescription' => "Order: " . $order_id,
						'amount' => $order->get_total() * 100,
						'currencyCode' => get_woocommerce_currency(),
						'name' => $_POST['name'],
						'billingAddress' => $billing_address,
						'customerOrderCode' => $order_id
					));
				}
				catch ( Exception $e )
				{
					wc_add_notice( __('Payment error:', 'woocommerce-gateway-worldpay') . ' ' . $e->getMessage(), 'error' );
					return;
				}

				if ( $response['paymentStatus'] === Worldpay_Response_States::SUCCESS ) {
					$order->payment_complete($response['orderCode']);
					$order->reduce_order_stock();
					WC()->cart->empty_cart();
					if($this->store_tokens
						&& $_POST['worldpay_save_card_details']
						&& !$_POST['worldpay_use_saved_card_details'])
					{
						update_user_meta( $currentUser->ID, 'worldpay_token', $_POST['worldpay_token'] );
					}
					return array(
						'result' => 'success',
						'redirect' => $this->get_return_url( $order )
					);
				} else {
					wc_add_notice( __('Payment error:', 'woocommerce-gateway-worldpay') . " " . $response['paymentStatusReason'], 'error' );
					return;
				}
			}

			public function process_refund( $order_id, $amount = null, $reason = '' ) {
				$order = wc_get_order( $order_id );

				if( $amount != $order->get_total() )
				{
					return new WP_Error(
						'partial-refund-error',
						'The Worldpay gateway plugin does not currently support partial refunds.'
					);
				}

				if ( ! $order || !$order->get_transaction_id() ) {
					return false;
				}
				try {
					$this->get_worldpay_client()->refundOrder($order->get_transaction_id());
					$order->add_order_note( __( 'Refunded' ));
					return true;
				} catch (WorldpayException $e) {
					return new WP_Error("refund-error", 'Refund failed.');
				}
			}

			public function handle_webhook() {
				if ( ! $this->notifications_enabled ) {
					return;
				}
				try{
					$webhookRequest = Worldpay_WebhookRequest::from_request();
					if( null == $webhookRequest )	{
						return;
					}
					$order = $this->get_order_from_order_code($webhookRequest->order_code);
					if ($this->is_test) {
						if ( "TEST" != $webhookRequest->environment ) {
							return;
						}
					} else {
						if ( "LIVE" != $webhookRequest->environment ) {
							return;
						}
					}
					if ( null == $order || null == $order->id ) {
						return;
					}
					switch ( $webhookRequest->paymentStatus )
					{
						case Worldpay_Response_States::SUCCESS:
							$order->payment_complete();
							$order->add_order_note( __( 'Payment successful' ));
							break;
						case Worldpay_Response_States::SETTLED:
							$order->add_order_note( __( 'Payment settled' ));
							break;
						case Worldpay_Response_States::FAILED:
							$order->update_status('failed');
							$order->add_order_note( __( 'Payment failed' ));
							break;
						case Worldpay_Response_States::REFUNDED:
							if ( 0 == $order->get_total_refunded() )
							{
								$order->add_order_note( __( 'Refunded' ));
								$args = array(
									'amount'	 => $order->get_total(),
									'reason'	 => "Order refunded in Worldpay",
									'order_id'   => $order->id,
									'line_items' => array()
								);
								wc_create_refund($args);
							}
							break;
						case Worldpay_Response_States::INFORMATION_REQUESTED:
							$order->add_order_note( __( 'Payment disputed - information requested.' ));
							break;
						case Worldpay_Response_States::INFORMATION_SUPPLIED:
							$order->add_order_note( __( 'Information received.' ));
							break;
						case Worldpay_Response_States::CHARGED_BACK:
							$order->add_order_note( __( 'Order charged back.' ));
							break;
					}
				}
				catch ( Exception $e )
				{
					// Suppress the exception, so the failing webhook is not resent.
				}
				return;
			}

			/**
			 * @param $orderCode
			 * @return WC_Order
			 */
			protected function get_order_from_order_code ( $order_code )
			{
				$args = array(
					'meta_query' => array(
						array(
							'key' => '_payment_method',
							'value' => 'WC_Gateway_Worldpay'
						),
						array(
							'key' => '_transaction_id',
							'value' => $order_code
						)
					),
					'post_status' => array_keys( wc_get_order_statuses() ),
					'post_type'   => 'shop_order'
				);
				$posts = get_posts( $args );
				if ( ! is_array($posts) || 1 != count($posts) ) {
					return null;
				}
				return new WC_Order($posts[0]);
			}

			protected function enqueue_checkout_scripts() {
				wp_enqueue_script('worldpay_script', 'https://cdn.worldpay.com/v1/worldpay.js', array('jquery', 'wc-checkout'));
				wp_enqueue_script(
					'worldpay_init', plugin_dir_url(__FILE__) . '/scripts/init_worldpay.js',
					array('jquery', 'wc-checkout', 'worldpay_script')
				);
				wp_localize_script('worldpay_init', 'WorldpayConfig', array('ClientKey' => $this->client_key));
			}

			protected function get_worldpay_client() {
				if ( ! isset($this->worldpay_client) ) {
					$this->worldpay_client = new Worldpay( $this->service_key );
				}
				return $this->worldpay_client;
			}

		}
	}
	load_plugin_textdomain( 'woocommerce-gateway-worldpay', false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) . 'languages' );
	add_action( 'plugins_loaded', 'init_woocommerce_worldpay_payment_gateway' );

	function woocommerce_add_worldpay_payment_gateway( $methods ) {
		$methods[] = 'WC_Gateway_Worldpay';
		return $methods;
	}
	add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_worldpay_payment_gateway' );
}
