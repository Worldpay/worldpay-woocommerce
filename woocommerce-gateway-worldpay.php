<?php
/**
 * Plugin Name: WooCommerce Worldpay Gateway - Worldpay Online Payments
 * Plugin URI:
 * Description: A plugin for integrating the worldpay payment gateway with Woo Commerce. Supports GBP, EUR and USD.
 * Version: 2.0.0
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
	require_once('libs/worldpay-wordpress-lib.php');
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
			protected $three_ds;
			protected $authorize_only;
			protected $notifications_enabled;
			protected $service_key;
			protected $api_endpoint;
			protected $js_endpoint;

			private $worldpay_client;
			protected $supported_currencies = array('GBP','EUR','USD');

			public function __construct()
			{
				$this->id = "WC_Gateway_Worldpay";
				$this->has_fields = true;
				$this->method_title = __( 'Worldpay', 'woocommerce-gateway-worldpay' );
				$this->method_description = __( 'The Worldpay payment gateway', 'woocommerce-gateway-worldpay' );

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
				add_action('woocommerce_receipt_' . $this->id, array(&$this, 'receipt_page'));
				add_action('woocommerce_thankyou_' . $this->id,array(&$this, 'thankyou_page'));

			}

			protected function init_keys()
			{
				$this->is_test = $this->get_option('is_test') != "no";
				$this->three_ds = $this->get_option('threeds_enabled') != "no";
				if ($this->get_option('paymentaction') == 'authorization') {
					$this->authorize_only = true;
				} else {
					$this->authorize_only = false;
				}


				$this->settlement_currency = $this->get_option('settlement_currency');

				$this->client_key = $this->is_test
					? $this->get_option('test_client_key')
					: $this->get_option('client_key');
				$this->service_key = $this->is_test
					? $this->get_option('test_service_key')
					: $this->get_option('service_key');


				$this->api_endpoint =  $this->get_option('api_endpoint')
					? $this->get_option('api_endpoint')
					: 'https://api.worldpay.com/v1/';

				$this->js_endpoint =  $this->get_option('js_endpoint')
					? $this->get_option('js_endpoint')
					: 'https://cdn.worldpay.com/v1/worldpay.js';

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

			private function store_token() {
				$currentUser = wp_get_current_user();
				if($this->store_tokens
						&& $_POST['worldpay_save_card_details']
					&& !$_POST['worldpay_use_saved_card_details'])
				{
					update_user_meta( $currentUser->ID, 'worldpay_token', $_POST['worldpay_token'] );
				}
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
				if (!WC()->session || !WC()->session->has_session()) {
					wc_add_notice(__('Payment error:', 'Please login', 'error'));
					return;
				}
				
				$order = new WC_Order( $order_id );

				if ( $_POST['worldpay_use_saved_card_details'] ) {
					$token = $this->get_stored_token();
				} else {
					$token = $_POST['worldpay_token'];
				}

				$name =  $order->billing_first_name . ' ' . $order->billing_last_name;

				if ($this->three_ds && $this->is_test && $name != 'NO 3DS') {
					$name = '3D';
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
					$sessionId = uniqid();
					WC()->session->set( 'wp_sessionid' , $sessionId );
					$this->get_worldpay_client()->setSessionId($sessionId);
					$response = $this->get_worldpay_client()->createOrder(array(
						'settlementCurrency' => $this->settlement_currency,
						'authoriseOnly' => $this->authorize_only,
						'token' => $token,
						'orderDescription' => "Order: " . $order_id,
						'amount' => $order->get_total() * 100,
						'currencyCode' => get_woocommerce_currency(),
						'name' => $name,
						'billingAddress' => $billing_address,
						'customerOrderCode' => $order_id,
						'is3DSOrder' => $this->three_ds
					));
				}
				catch ( Exception $e )
				{
					wc_add_notice( __('Payment error:', 'woocommerce-gateway-worldpay') . ' ' . $e->getMessage(), 'error' );
					return;
				}

				if ( $response['paymentStatus'] === Worldpay_Response_States::SUCCESS || $response['paymentStatus'] === Worldpay_Response_States::AUTHORIZED) {
					$order->payment_complete($response['orderCode']);
					$order->reduce_order_stock();
					WC()->cart->empty_cart();
					$this->store_token();
					return array(
						'result' => 'success',
						'redirect' => $this->get_return_url( $order )
					);
				}
				else if ($response['is3DSOrder'] && $response['paymentStatus'] == Worldpay_Response_States::PRE_AUTHORIZED) {
					$this->store_token();
					if (!add_post_meta( $order_id, '_transaction_id', $response['orderCode'], true )) {
						update_post_meta ( $order_id, '_transaction_id', $response['orderCode'] );
					}
					WC()->session->set( 'wp_order' , $response );
					return array(
						'result' => 'success',
						'redirect' =>  $order->get_checkout_payment_url( true )
					);
				} else {
					wc_add_notice( __('Payment error:', 'woocommerce-gateway-worldpay') . " " . $response['paymentStatusReason'], 'error' );
					return;
				}
			}

			public function receipt_page($order_id)
			{
				$response = WC()->session->get( 'wp_order');
				if ($response) {
					$order = new WC_Order($order_id);
					Worldpay_PaymentForm::three_ds_redirect($response, $order);
				}
			}

			public function thankyou_page($order_id) 
			{
				$response = WC()->session->get( 'wp_order');
				if ($response) {
					WC()->session->set( 'wp_order', false);
					$order = new WC_Order($order_id);
					try {

						$orderCode = get_post_meta( $order_id, '_transaction_id', true );

						$sessionId = WC()->session->get( 'wp_sessionid');
						$this->get_worldpay_client()->setSessionId($sessionId);

						$response = $this->get_worldpay_client()->authorise3DSOrder($orderCode, $_POST['PaRes']);
						if (isset($response['paymentStatus']) && ($response['paymentStatus'] === Worldpay_Response_States::SUCCESS ||  $response['paymentStatus'] === Worldpay_Response_States::AUTHORIZED)) {
							$order->payment_complete($orderCode);
							$order->reduce_order_stock();
						} else {
							WC()->session->set( 'wp_error',  __('Payment error: Problem authorising 3DS order', 'woocommerce-gateway-worldpay'));
							wp_redirect($order->get_checkout_payment_url( true ));
							exit;
						}

					} catch (WorldpayException $e) {
						WC()->session->set( 'wp_error',  __('Payment error: 3DS Authentication failed, please try again', 'woocommerce-gateway-worldpay'));
						wp_redirect($order->get_checkout_payment_url(  ));
						exit;
					}
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

			protected function process_status($status, $order) {
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
					$this->process_status($webhookRequest->paymentStatus, $order);
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
				wp_enqueue_script('worldpay_script', $this->js_endpoint, array('jquery', 'wc-checkout'));
				wp_enqueue_script(
					'worldpay_init', plugin_dir_url(__FILE__) . '/scripts/init_worldpay.js?version=' . '2.0.0',
					array('jquery', 'wc-checkout', 'worldpay_script')
				);
				wp_localize_script('worldpay_init', 'WorldpayConfig', array('ClientKey' => $this->client_key));
			}

			protected function get_worldpay_client() {
				if ( ! isset($this->worldpay_client) ) {
					$this->worldpay_client = new WordpressWorldpay( $this->service_key );
					$this->worldpay_client->setEndpoint($this->api_endpoint);
					$this->worldpay_client->setPluginData('WooCommerce', '2.0.0');
				}
				return $this->worldpay_client;
			}

		}
		class WC_Gateway_Worldpay_Paypal extends WC_Gateway_Worldpay {
			private $worldpay_client;
			public function __construct()
			{
				$this->id = "WC_Gateway_Worldpay";
				$this->has_fields = true;
				$this->method_title = __( 'Worldpay PayPal', 'woocommerce-gateway-worldpay' );
				$this->method_description = __( 'The Worldpay PayPal payment gateway, please setup your keys and other settings within the main Worldpay settings.', 'woocommerce-gateway-worldpay' );

				$this->supports = array(
					'products',
					'refunds'
				);

				
				$this->init_keys();

				$this->id = "WC_Gateway_Worldpay_Paypal";

				$this->init_form_fields();
				$this->init_settings();

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
			//	add_action('woocommerce_receipt_' . $this->id, array(&$this, 'receipt_page'));
				add_action('woocommerce_thankyou_' . $this->id,array(&$this, 'thankyou_page'));

			}
			public function init_form_fields()
			{
				$this->form_fields = Worldpay_AdminForm::get_paypal_admin_form_fields();
			}
			public function payment_fields()
			{

				Worldpay_PaymentForm::render_paypal_form();
			}

			public function process_payment( $order_id )
			{
				if (!WC()->session || !WC()->session->has_session()) {
					wc_add_notice(__('Payment error:', 'Please login', 'error'));
					return;
				}

				$order = new WC_Order( $order_id );
				$token = $_POST['worldpay_token'];

				$name =  $order->billing_first_name . ' ' . $order->billing_last_name;

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
					$response = $this->get_worldpay_client()->createApmOrder(array(
						'settlementCurrency' => $this->settlement_currency,
						'token' => $token,
						'orderDescription' => "Order: " . $order_id,
						'amount' => $order->get_total() * 100,
						'currencyCode' => get_woocommerce_currency(),
						'name' => $name,
						'billingAddress' => $billing_address,
						'customerOrderCode' => $order_id,
						'successUrl' =>  add_query_arg( 'status', 'success', $order->get_checkout_order_received_url()) . '&',
						'pendingUrl' =>  add_query_arg( 'status', 'pending', $order->get_checkout_order_received_url()). '&',
						'failureUrl' =>   add_query_arg( 'status', 'failure', $order->get_checkout_order_received_url()). '&',
						'cancelUrl' =>  add_query_arg( 'wp_cancel', '1', $order->get_checkout_payment_url()). '&'
					));
				}
				catch ( Exception $e )
				{
					wc_add_notice( __('Payment error:', 'woocommerce-gateway-worldpay') . ' ' . $e->getMessage(), 'error' );
					return;
				}

				if ($response['paymentStatus'] == Worldpay_Response_States::PRE_AUTHORIZED) {
					if (!add_post_meta( $order_id, '_transaction_id', $response['orderCode'], true )) {
						update_post_meta ( $order_id, '_transaction_id', $response['orderCode'] );
					}
					WC()->session->set( 'wp_order' , $response );
					return array(
						'result' => 'success',
						'redirect' =>  $response['redirectURL']
					);
				} else {
					wc_add_notice( __('Payment error:', 'woocommerce-gateway-worldpay') . " " . $response['paymentStatusReason'], 'error' );
					return;
				}
			}

			public function thankyou_page($order_id) 
			{
				$response = WC()->session->get( 'wp_order');
				if ($response) {
					WC()->session->set( 'wp_order', false);
					$order = new WC_Order($order_id);
					
					$status = get_query_var('status', '');
	
					if ($status == 'failure') {
						WC()->session->set( 'wp_error',  __('Payment error: Payment failed, please try again', 'woocommerce-gateway-worldpay'));
						WC()->session->save_data();
						wp_redirect( $order->get_checkout_payment_url());
						exit;
					}
					try {
						$wpOrder = $this->get_worldpay_client()->getOrder($response['orderCode']);
						if (isset($wpOrder['paymentStatus']) && ($wpOrder['paymentStatus'] === Worldpay_Response_States::SUCCESS ||  $wpOrder['paymentStatus'] === Worldpay_Response_States::AUTHORIZED)) {
							$order->payment_complete($response['orderCode']);
							$order->reduce_order_stock();
						}
					} catch (WorldpayException $e) {
						WC()->session->set( 'wp_error',  __('Payment error: ' . $e->getMessage(), 'woocommerce-gateway-worldpay'));
						wp_redirect( $order->get_checkout_payment_url());
						exit;
					}
				}
			}
		}         
		class WC_Gateway_Worldpay_Giropay extends WC_Gateway_Worldpay_Paypal {
			private $worldpay_client;
			protected $supported_currencies = array('EUR');
			public function __construct()
			{
				$this->id = "WC_Gateway_Worldpay";
				$this->has_fields = true;
				$this->method_title = __( 'Worldpay Giropay', 'woocommerce-gateway-worldpay' );
				$this->method_description = __( 'The Worldpay Giropay payment gateway, please setup your keys and other settings within the main Worldpay settings.', 'woocommerce-gateway-worldpay' );

				$this->supports = array(
					'products',
					'refunds'
				);
				
				$this->init_keys();

				$this->id = "WC_Gateway_Worldpay_Giropay";

				$this->init_form_fields();
				$this->init_settings();

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
				add_action('woocommerce_thankyou_' . $this->id,array(&$this, 'thankyou_page'));

			}
			public function init_form_fields()
			{
				$this->form_fields = Worldpay_AdminForm::get_giropay_admin_form_fields();
			}
			public function payment_fields()
			{
				Worldpay_PaymentForm::render_giropay_form();
			}
		}
	}
	load_plugin_textdomain( 'woocommerce-gateway-worldpay', false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) . 'languages' );
	add_action( 'plugins_loaded', 'init_woocommerce_worldpay_payment_gateway' );

	function woocommerce_add_worldpay_payment_gateway( $methods ) {
		if (WC()->session) {
			$error = WC()->session->get( 'wp_error');;
			if (!isset($_POST['worldpay_token']) && is_wc_endpoint_url( 'order-pay' ) && $error) {;
				wc_add_notice($error, 'error');
				WC()->session->set( 'wp_error', false);
			}
		} 
		$methods[] = 'WC_Gateway_Worldpay';
		$methods[] = 'WC_Gateway_Worldpay_Paypal';
		$methods[] = 'WC_Gateway_Worldpay_Giropay';
		return $methods;
	}
	function add_query_vars_filter( $vars ){
	  $vars[] = "status";
	  return $vars;
	}
	add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_worldpay_payment_gateway' );
	add_filter( 'query_vars', 'add_query_vars_filter' );

}
