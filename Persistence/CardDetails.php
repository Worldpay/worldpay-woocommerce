<?php

class WorldPay_CardDetails
{
    public $token;
    public $maskedCardNumber;
    public function __construct($cardDetails, $token)
    {
        $this->token = $token;
        $this->maskedCardNumber = $cardDetails['maskedCardNumber'];
    }

    public static function GetByUser($user, Worldpay $worldPayClient)
    {
        if($user == null){
            return null;
        }

        $token = WorldPay_Token::GetByUser($user);

        if($token == null)
        {
            return null;
        }

        try{
            return new WorldPay_CardDetails($worldPayClient->getStoredCardDetails($token), $token);
        }
        catch(Exception $e)
        {
            return null;
        }
    }
} 