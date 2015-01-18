<?php

/**
 * @package   andreoli/api-proxy-laravel
 * @author    Michele Andreoli <michi.andreoli[at]gmail.com>
 * @copyright Copyright (c) Michele Andreoli
 * @license   http://mit-license.org/
 * @link      https://github.com/mandreoli/api-proxy-laravel
 */

namespace Andreoli\ApiProxy;

use Andreoli\ApiProxy\Exceptions\ProxyMissingParamException;

class Proxy {

    private $uri_param = null;

    public function __construct($uri_param) {
        $this->uri_param = $uri_param;
    }

    /**
     * @param $inputs Input data
     */
    public function makeRequest($inputs) {

        //Check if URI param exists in the input data array
        if (!array_key_exists($this->uri_param, $inputs)) {
            throw new ProxyMissingParamException($this->uri_param);
        }
        $uri = $inputs[$this->uri_param];


        return $uri;
    }

}
