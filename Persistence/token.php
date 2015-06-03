<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Worldpay_Token
{
	public static function get_by_user($user)
	{
		if($user == null){
			return null;
		}

		$tokens = get_user_meta($user->ID, 'worldpay_token');
		if(!is_array($tokens) || count($tokens) == 0){
			return null;
		}

		return $tokens[0];
	}
}
