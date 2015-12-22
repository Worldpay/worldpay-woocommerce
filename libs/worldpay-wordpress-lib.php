<?php
require_once("worldpay-lib-php-1.8.0/worldpay.php");

class WordpressWorldpay extends Worldpay {
	
	// Use wordpress methods for remote request
	private function sendRequest($action, $json = false, $expectResponse = false, $method = 'POST')
    {

        $arch = (bool)((1<<32)-1) ? 'x64' : 'x86';

        $clientUserAgent = 'os.name=' . php_uname('s') . ';os.version=' . php_uname('r') . ';os.arch=' .
        $arch . ';lang.version='. phpversion() . ';lib.version=1.8.0;' .
        'api.version=v1;lang=php;owner=worldpay';

        if ($this->pluginName) {
             $clientUserAgent .= ';plugin.name=' + $this->pluginName;
        }
        if ($this->pluginVersion) {
             $clientUserAgent .= ';plugin.version=' + $this->pluginVersion;
        }

        $ars = array();

        $response = wp_remote_request( $this->endpoint.$action, array(
            'method' => $method,
            'timeout' => $this->timeout,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array(
                "Authorization"=> "$this->service_key",
                "Content-Type"=> "application/json",
                "X-wp-client-user-agent"=> "$clientUserAgent",
                "Content-Length"=> strlen($json)
            ),
            'body' => $json,
            'cookies' => array()
            )
        );

        if ( is_wp_error( $response ) ) {
            $errorResponse = $response->get_error_message();

            self::onError('http', false, $response['response']['code'], null, $response['response']['message']);

            $original_response = "";
        } else {
            $original_response = $response;
            $response = self::handleResponse($response['body']);
        }

        // Check JSON has decoded correctly
        if ($expectResponse && ($response === null || $response === false )) {
            self::onError('uanv', self::$errors['json'], 503);
        }

        // Check the status code exists
        if (isset($response["httpStatusCode"])) {

            if ($response["httpStatusCode"] != 200) {
                self::onError(
                    false,
                    $response["message"],
                    $original_response['response']['code'],
                    $response['httpStatusCode'],
                    $response['description'],
                    $response['customCode']
                );

            }

        } elseif ($expectResponse && $original_response['response']['code'] != 200) {
            // If we expect a result and we have an error
            self::onError('uanv', self::$errors['json'], 503);

        } elseif (!$expectResponse) {

            if ($original_response['response']['code'] != 200) {
                self::onError('apierror', $result, $original_response['response']['code']);
            } else {
                $response = true;
            }
        }

        return $response;
    }

    public function getShopperAcceptHeader()
    {
       return '*/*';
    }
}
