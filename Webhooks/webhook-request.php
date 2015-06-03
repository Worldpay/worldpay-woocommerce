<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Created by PhpStorm.
 * User: RAS
 * Date: 30/01/15
 * Time: 17:05
 */

class Worldpay_WebhookRequest {
	public $order_code;
	public $paymentStatus;
	public $environment;

	public function __construct( $webhook_data )
	{
		$this->order_code = $webhook_data['orderCode'];
		$this->paymentStatus = $webhook_data['paymentStatus'];
		$this->environment = $webhook_data['environment'];
	}

	public static function from_request()
	{
        $webhook_data = json_decode( file_get_contents('php://input'), true );
		if ( $webhook_data != null && isset($webhook_data['orderCode'] )
			&& isset( $webhook_data['environment'] )
			&& isset( $webhook_data['paymentStatus'] ) )
		{
			return new Worldpay_WebhookRequest( $webhook_data );
		}
		return null;
	}
}
