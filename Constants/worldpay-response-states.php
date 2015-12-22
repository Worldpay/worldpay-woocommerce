<?php
/**
 * Created by PhpStorm.
 * User: RAS
 * Date: 30/01/15
 * Time: 17:02
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Worldpay_Response_States {
	const SUCCESS = 'SUCCESS';
	const AUTHORIZED = 'AUTHORIZED';
	const PRE_AUTHORIZED = 'PRE_AUTHORIZED';
	const FAILED = 'FAILED';
	const REFUNDED = 'REFUNDED';
	const SETTLED = 'SETTLED';
    const CHARGED_BACK = 'CHARGED_BACK';
    const INFORMATION_REQUESTED = 'INFORMATION_REQUESTED';
	const INFORMATION_SUPPLIED = 'INFORMATION_SUPPLIED';
}
