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
class ProxyMissingParamException extends ProxyException {

    /**
     * Throw a ProxyMissingParamException exception
     */
    public function __construct($parameter) {
	    $this->httpStatusCode = 400;
	    $this->errorType = 'proxy_missing_param';
        parent::__construct(\Lang::get('api-proxy-laravel::messages.proxy_missing_param', array('param' => $parameter)));
    }

}
