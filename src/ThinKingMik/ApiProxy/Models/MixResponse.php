<?php

/**
 * @package   thinkingmik/api-proxy-laravel
 * @author    Michele Andreoli <michi.andreoli[at]gmail.com>
 * @copyright Copyright (c) Michele Andreoli
 * @license   http://mit-license.org/
 * @link      https://github.com/thinkingmik/api-proxy-laravel
 */

namespace ThinKingMik\ApiProxy\Models;

class MixResponse {

    private $response = null;
    private $cookie = null;

    public function __construct($response = null, $cookie = null) {
        $this->response = $response;
        $this->cookie = $cookie;
    }

    public function setResponse($response) {
        $this->response = $response;
    }

    public function setCookie($cookie) {
        $this->cookie = $cookie;
    }

    public function getResponse() {
        return $this->response;
    }

    public function getCookie() {
        return $this->cookie;
    }
}
