<?php

/**
 * @package   andreoli/api-proxy-laravel
 * @author    Michele Andreoli <michi.andreoli[at]gmail.com>
 * @copyright Copyright (c) Michele Andreoli
 * @license   http://mit-license.org/
 * @link      https://github.com/mandreoli/api-proxy-laravel
 */

namespace Andreoli\ApiProxy\Exceptions;

/**
 * Exception class
 */
class CookieInvalidException extends ProxyException {

    /**
     * Throw a CookieInvalidException exception
     */
    public function __construct($parameter) {
	    $this->httpStatusCode = 500;
	    $this->errorType = 'proxy_cookie_invalid';
        parent::__construct(\Lang::get('api-proxy-laravel::messages.proxy_cookie_invalid', array('param' => $parameter)));
    }

}
