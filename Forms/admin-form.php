<?php

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
			)
		);
	}
}
