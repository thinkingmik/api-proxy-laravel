<?php

/**
 * @package   thinkingmik/api-proxy-laravel
 * @author    Michele Andreoli <michi.andreoli[at]gmail.com>
 * @copyright Copyright (c) Michele Andreoli
 * @license   http://mit-license.org/
 * @link      https://github.com/thinkingmik/api-proxy-laravel
 */

namespace ThinKingMik\ApiProxy\Exceptions;

/**
 * Exception class
 */
class ResponseParseErrorException extends ProxyException {

    public function __construct() {
	    $this->httpStatusCode = 500;
	    $this->errorType = 'proxy_response_parse_error';
        parent::__construct(\Lang::get('api-proxy-laravel::messages.proxy_response_parse_error'));
    }

}
