<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Worldpay_AdminForm
{
	public static function get_admin_form_fields()
	{
		return array(
			'enabled' => array(
				'title' => __( 'Enable/Disable', 'woocommerce-gateway-worldpay' ),
				'type' => 'checkbox',
				'label' => __( 'Enable Worldpay', 'woocommerce-gateway-worldpay' ),
				'default' => 'yes'
			),
			'is_test' => array(
				'title' => __( 'Testing', 'woocommerce-gateway-worldpay' ),
				'type' => 'checkbox',
				'label' => __( 'Use test settings', 'woocommerce-gateway-worldpay' ),
				'default' => 'no'
			),
			'paymentaction' => array(
				'title'       => __( 'Payment Action', 'woocommerce' ),
				'type'        => 'select',
				'class'       => 'wc-enhanced-select',
				'description' => __( 'Choose whether you wish to capture funds immediately or authorize payment only.', 'woocommerce' ),
				'default'     => 'sale',
				'desc_tip'    => true,
				'options'     => array(
					'sale'          => __( 'Capture', 'woocommerce' ),
					'authorization' => __( 'Authorize', 'woocommerce' )
				)
			),
			'threeds_enabled' => array(
				'title' => __( '3DS Enabled', 'woocommerce-gateway-worldpay' ),
				'type' => 'checkbox',
				'label' => __( 'Enable 3Ds', 'woocommerce-gateway-worldpay' ),
				'default' => 'no'
			),
			'title' => array(
				'title' => __( 'Title', 'woocommerce-gateway-worldpay' ),
				'type' => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-worldpay' ),
				'default' => __( 'Worldpay', 'woocommerce-gateway-worldpay' ),
				'desc_tip'	  => true,
			),
			'description' => array(
				'title' => __( 'Customer Message', 'woocommerce-gateway-worldpay' ),
				'type' => 'textarea',
				'default' => 'Pay with Worldpay'
			),
			'store_tokens' => array(
				'title' => __( 'Card-on-file Payment', 'woocommerce-gateway-worldpay' ),
				'type' => 'checkbox',
				'label' => __( 'Enable card-on-file payment', 'woocommerce-gateway-worldpay' ),
				'default' => 'no'
			),
			'notifications_enabled' => array(
				'title' => __( 'Webhooks', 'woocommerce-gateway-worldpay' ),
				'type' => 'checkbox',
				'label' => __( 'Enable webhooks', 'woocommerce-gateway-worldpay' ),
				'default' => 'no',
                'description' => "Webhook URL: " . site_url() . "/?s=word&wc-api=WC_Gateway_Worldpay"
			),
			'settlement_currency' => array(
                'title' => __( 'Settlement Currency', 'woocommerce-gateway-worldpay' ),
                'type' => 'select',
                'default' => 'GBP',
                'options' => get_woocommerce_currencies()
            ),
            'service_key' => array(
                'title' => __( 'Service Key', 'woocommerce-gateway-worldpay' ),
                'type' => 'text',
                'default' => ''
            ),
			'client_key' => array(
				'title' => __( 'Client Key', 'woocommerce-gateway-worldpay' ),
				'type' => 'text',
				'default' => ''
			),
            'test_service_key' => array(
                'title' => __( 'Test Service Key', 'woocommerce-gateway-worldpay' ),
                'type' => 'text',
                'default' => ''
            ),
			'test_client_key' => array(
				'title' => __( 'Test Client Key', 'woocommerce-gateway-worldpay' ),
				'type' => 'text',
				'default' => ''
			),
            
			
		);
	}

	public static function get_paypal_admin_form_fields()
	{
		return array(
			'enabled' => array(
				'title' => __( 'Enable/Disable', 'woocommerce-gateway-worldpay' ),
				'type' => 'checkbox',
				'label' => __( 'Enable Worldpay PayPal', 'woocommerce-gateway-worldpay' ),
				'default' => 'yes'
			),
			'title' => array(
				'title' => __( 'Title', 'woocommerce-gateway-worldpay' ),
				'type' => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-worldpay' ),
				'default' => __( 'Worldpay PayPal', 'woocommerce-gateway-worldpay' ),
				'desc_tip'	  => true,
			)
		);
	}

	public static function get_giropay_admin_form_fields()
	{
		return array(
			'enabled' => array(
				'title' => __( 'Enable/Disable', 'woocommerce-gateway-worldpay' ),
				'type' => 'checkbox',
				'label' => __( 'Enable Worldpay Giropay', 'woocommerce-gateway-worldpay' ),
				'default' => 'yes'
			),
			'title' => array(
				'title' => __( 'Title', 'woocommerce-gateway-worldpay' ),
				'type' => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-worldpay' ),
				'default' => __( 'Worldpay Giropay', 'woocommerce-gateway-worldpay' ),
				'desc_tip'	  => true,
			)
		);
	}
}
