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
class ProxyGenericException extends ProxyException {

    /**
     * Throw a ProxyException exception
     */
    public function __construct($message) {
	    $this->httpStatusCode = 500;
	    $this->errorType = 'proxy_generic_error';
        parent::__construct($message);
    }

}
