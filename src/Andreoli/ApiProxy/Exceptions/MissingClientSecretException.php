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
class MissingClientSecretException extends ProxyException {

    /**
     * Throw a new MissingClientSecretException exception
     */
    public function __construct($parameter) {
	    $this->httpStatusCode = 500;
	    $this->errorType = 'missing_client_secret';
        parent::__construct(\Lang::get('api-proxy-laravel::messages.missing_client_secret', array('client' => $parameter)));
    }

}
