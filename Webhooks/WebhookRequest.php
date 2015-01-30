<?php
/**
 * Created by PhpStorm.
 * User: RAS
 * Date: 30/01/15
 * Time: 17:05
 */

class WorldPay_WebhookRequest {
    public $orderCode;
    public $paymentStatus;
    public $environment;

    public function __construct($webHookData)
    {
        $this->orderCode = $webHookData['orderCode'];
        $this->paymentStatus = $webHookData['paymentStatus'];
        $this->environment = $webHookData['environment'];
    }

    public static function FromRequest()
    {
        $webHookData = json_decode(file_get_contents('php://input'), true);
        if($webHookData != null && isset($webHookData['orderCode'])
            && isset($webHookData['environment'])
            && isset($webHookData['paymentStatus']))
        {
            return new WorldPay_WebhookRequest($webHookData);
        }
        return null;
    }
} 