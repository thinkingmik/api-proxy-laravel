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
class CookieExpiredException extends ProxyException {

    /**
     * Throw a new CookieExpiredException exception
     */
    public function __construct() {
	    $this->httpStatusCode = 403;
	    $this->errorType = 'proxy_cookie_expired';
        parent::__construct(\Lang::get('api-proxy-laravel::messages.proxy_cookie_expired'));
    }

}
