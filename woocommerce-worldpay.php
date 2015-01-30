<?php
/**
 * Plugin Name: WooCommerce-WorldPay
 * Plugin URI:
 * Description: A plugin for integrating the worldpay payment gateway with Woo Commerce. Currently supports GBP, EUR and USD.
 * Version: 1.0
 * Author: WorldPay
 * Author URI:
 * Requires at least: 4.0
 * Tested up to: 4.1
 *
 *
 * @package WooCommerce-WorldPay
 * @category Core
 * @author WorldPay
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
    require_once('libs/worldpay-lib-php-1.2/lib/worldpay.php');
    require_once('Persistence/Token.php');
    require_once('Persistence/CardDetails.php');
    require_once('Forms/PaymentForm.php');
    require_once('Forms/AdminForm.php');
    require_once('Webhooks/WebhookRequest.php');
    require_once('Constants/WorldPay_Response_States.php');

    function init_woocommerce_worldpay_payment_gateway() {
        class WC_Gateway_WorldPay extends WC_Payment_Gateway {
            protected $client_key;
            protected $store_tokens;
            protected $notifications_enabled;
            protected $server_key;
            private $worldpay_client;
            protected $supported_currencies = array('GBP','EUR','USD');

            public function __construct()
            {
                $this->id = "WC_Gateway_WorldPay";
                $this->has_fields = true;
                $this->method_title = "WorldPay";
                $this->method_description = "The WorldPay payment gateway";

                $this->supports = array(
                    'products',
                    'refunds'
                );

                $this->init_form_fields();
                $this->init_settings();
                if(!in_array(get_woocommerce_currency(), $this->supported_currencies))
                {
                    $this->enabled = false;
                }
                $this->init_keys();
                $this->title = $this->get_option( 'title' );
                $this->store_tokens = $this->get_option( 'store_tokens' ) != "no";
                $this->notifications_enabled = $this->get_option( 'notifications_enabled' ) != "no";

                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

                if (is_checkout())
                {
                    $this->enqueue_checkout_scripts();
                }

                add_action( 'woocommerce_api_wc_gateway_worldpay', array( $this, 'handle_webhook' ) );
            }

            protected function init_keys()
            {
                $this->client_key = $this->get_option('is_test')
                    ? $this->get_option('test_client_key')
                    : $this->get_option('client_key');
                $this->server_key = $this->get_option('is_test')
                    ? $this->get_option('test_server_key')
                    : $this->get_option('server_key');
            }

            public function init_form_fields()
            {
                $this->form_fields = WorldPay_AdminForm::get_admin_form_fields();
            }

            public function payment_fields()
            {
                WorldPay_PaymentForm::render_payment_form(
                    $this->store_tokens,
                    $this->get_stored_card_details()
                );
            }

            private function get_stored_card_details()
            {
                if(!$this->store_tokens){
                    return null;
                }

                $currentUser = wp_get_current_user();
                return WorldPay_CardDetails::GetByUser($currentUser, $this->get_worldpay_client());
            }

            private function get_stored_token()
            {
                if(!$this->store_tokens){
                    return null;
                }

                $currentUser = wp_get_current_user();
                return WorldPay_Token::GetByUser($currentUser);
            }

            public function process_payment($order_id)
            {
                global $woocommerce;
                $currentUser = wp_get_current_user();
                $order = new WC_Order( $order_id );

                if ($_POST['worldpay_use_saved_card_details'])
                {
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

                try{
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
                catch(Exception $e)
                {
                    wc_add_notice( __('Payment error:', 'woothemes') . " Payment failed.", 'error' );
                    return;
                }

                if ($response['paymentStatus'] === WorldPay_Response_States::SUCCESS) {
                    $order->payment_complete($response['orderCode']);
                    $order->reduce_order_stock();
                    $woocommerce->cart->empty_cart();
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
                    wc_add_notice( __('Payment error:', 'woothemes') . " Payment failed.", 'error' );
                    return;
                }
            }

            public function process_refund( $order_id, $amount = null, $reason = '' ) {
                $order = wc_get_order( $order_id );

                if($amount != $order->get_total())
                {
                    return new WP_Error(
                        'partial-refund-error',
                        'The WorldPay payment gateway does not support partial refunds.'
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

            public function handle_webhook(){
                if (!$this->notifications_enabled) {
                    return;
                }
                try{
                    $webhookRequest = WorldPay_WebhookRequest::FromRequest();
                    if($webhookRequest == null)
                    {
                        return;
                    }
                    $order = $this->get_order_from_order_code($webhookRequest->orderCode);
                    if($order == null || $order->id == null)
                    {
                        return;
                    }
                    switch($webhookRequest->paymentStatus)
                    {
                        case WorldPay_Response_States::SUCCESS:
                            $order->payment_complete();
                            $order->add_order_note( __( 'Payment successful' ));
                            break;
                        case WorldPay_Response_States::SETTLED:
                            $order->add_order_note( __( 'Payment settled' ));
                            break;
                        case WorldPay_Response_States::FAILED:
                            $order->update_status('failed');
                            $order->add_order_note( __( 'Payment failed' ));
                            break;
                        case WorldPay_Response_States::REFUNDED:
                            if($order->get_total_refunded() == 0)
                            {
                                $order->add_order_note( __( 'Refunded' ));
                                $args = array(
                                    'amount'     => $order->get_total(),
                                    'reason'     => "Order refunded in WorldPay",
                                    'order_id'   => $order->id,
                                    'line_items' => array()
                                );
                                wc_create_refund($args);
                            }
                            break;
                        case WorldPay_Response_States::INFORMATION_REQUESTED:
                            $order->add_order_note( __( 'Payment disputed - information requested.' ));
                            break;
                        case WorldPay_Response_States::INFORMATION_SUPPLIED:
                            $order->add_order_note( __( 'Information received.' ));
                            break;
                        case WorldPay_Response_States::CHARGED_BACK:
                            $order->add_order_note( __( 'Order charged back.' ));
                            break;
                    }
                }
                catch (Exception $e)
                {
                    // Suppress the exception, so the failing webhook is not resent.
                }
                return;
            }

            /**
             * @param $orderCode
             * @return WC_Order
             */
            protected function get_order_from_order_code($orderCode)
            {
                $args = array(
                    'meta_query' => array(
                        array(
                            'key' => '_payment_method',
                            'value' => 'WC_Gateway_WorldPay'
                        ),
                        array(
                            'key' => '_transaction_id',
                            'value' => $orderCode
                        )
                    ),
                    'post_status' => array_keys( wc_get_order_statuses() ),
                    'post_type'   => 'shop_order'
                );
                $posts = get_posts($args);
                if(!is_array($posts) || count($posts) != 1)
                {
                    return null;
                }
                return new WC_Order($posts[0]);
            }

            protected function enqueue_checkout_scripts()
            {
                wp_enqueue_script('worldpay_script', 'https://cdn.worldpay.com/v1/worldpay.js', array('jquery', 'wc-checkout'));
                wp_enqueue_script(
                    'worldpay_init', plugin_dir_url(__FILE__) . '/scripts/init_worldpay.js',
                    array('jquery', 'wc-checkout', 'worldpay_script')
                );
                wp_localize_script('worldpay_init', 'WorldPayConfig', array('ClientKey' => $this->client_key));
            }

            protected function get_worldpay_client()
            {
                if ($this->worldpay_client == null)
                {
                    $this->worldpay_client = new Worldpay($this->server_key);
                }
                return $this->worldpay_client;
            }

        }
    }
    add_action( 'plugins_loaded', 'init_woocommerce_worldpay_payment_gateway' );

    function woocommerce_add_worldpay_payment_gateway( $methods ) {
        $methods[] = 'WC_Gateway_WorldPay';
        return $methods;
    }
    add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_worldpay_payment_gateway' );
}