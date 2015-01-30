<?php

class WorldPay_AdminForm
{
    public static function get_admin_form_fields()
    {
        return array(
            'enabled' => array(
                'title' => __( 'Enable/Disable', 'woocommerce' ),
                'type' => 'checkbox',
                'label' => __( 'Enable WorldPay', 'woocommerce' ),
                'default' => 'yes'
            ),
            'is_test' => array(
                'title' => __( 'Testing', 'woocommerce' ),
                'type' => 'checkbox',
                'label' => __( 'Use test settings', 'woocommerce' ),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __( 'Title', 'woocommerce' ),
                'type' => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                'default' => __( 'WorldPay', 'woocommerce' ),
                'desc_tip'      => true,
            ),
            'description' => array(
                'title' => __( 'Customer Message', 'woocommerce' ),
                'type' => 'textarea',
                'default' => 'Pay with WorldPay'
            ),
            'store_tokens' => array(
                'title' => __( 'Card-on-file Payment', 'woocommerce' ),
                'type' => 'checkbox',
                'label' => __( 'Enable card-on-file payment', 'woocommerce' ),
                'default' => 'no'
            ),
            'notifications_enabled' => array(
                'title' => __( 'Webhooks', 'woocommerce' ),
                'type' => 'checkbox',
                'label' => __( 'Enable webhooks', 'woocommerce' ),
                'default' => 'no'
            ),
            'client_key' => array(
                'title' => __( 'Client Key', 'woocommerce' ),
                'type' => 'text',
                'default' => ''
            ),
            'server_key' => array(
                'title' => __( 'Server Key', 'woocommerce' ),
                'type' => 'text',
                'default' => ''
            ),
            'test_client_key' => array(
                'title' => __( 'Test Client Key', 'woocommerce' ),
                'type' => 'text',
                'default' => ''
            ),
            'test_server_key' => array(
                'title' => __( 'Test Server Key', 'woocommerce' ),
                'type' => 'text',
                'default' => ''
            )
        );
    }
} 