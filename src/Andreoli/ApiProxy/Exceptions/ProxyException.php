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
class ProxyException extends \Exception {

    /**
     * The HTTP status code for this exception that should be sent in the response
     */
    public $httpStatusCode = 400;

    /**
     * If true the server should redirect back to the client
     * @var boolean
     */
    public $serverShouldRedirect = false;

    /**
     * The exception type
     */
    public $errorType = '';

    /**
     * Throw a new exception
     */
    public function __construct($msg = 'An error occured') {
        parent::__construct($msg);
    }

    /**
     * Should the server redirect back to the client?
     * @return bool
     */
    public function shouldRedirect() {
        return $this->serverShouldRedirect;
    }

    /**
     * Get all headers that have to be send with the error response
     * @return array Array with header values
     */
    public function getHttpHeaders() {
        $headers = [];
        switch ($this->httpStatusCode) {
            case 401:
                $headers[] = 'HTTP/1.1 401 Unauthorized';
                break;
            case 403:
                $headers[] = 'HTTP/1.1 403 Forbidden';
                break;
            case 500:
                $headers[] = 'HTTP/1.1 500 Internal Server Error';
                break;
            case 501:
                $headers[] = 'HTTP/1.1 501 Not Implemented';
                break;
            case 400:
            default:
                $headers[] = 'HTTP/1.1 400 Bad Request';
                break;
        }

        return $headers;
    }

}
