<?php

/**
 * @package   thinkingmik/api-proxy-laravel
 * @author    Michele Andreoli <michi.andreoli[at]gmail.com>
 * @copyright Copyright (c) Michele Andreoli
 * @license   http://mit-license.org/
 * @link      https://github.com/thinkingmik/api-proxy-laravel
 */

namespace ThinKingMik\ApiProxy\Managers;

use ThinKingMik\ApiProxy\Exceptions\CookieExpiredException;
use ThinKingMik\ApiProxy\Exceptions\CookieInvalidException;
use Illuminate\Support\Facades\Cookie;
use ThinKingMik\ApiProxy\ProxyAux;

class CookieManager {

    const COOKIE_NAME = 'name';
    const COOKIE_TIME = 'time';
    private $info = null;

    public function __construct($info) {
        $this->info = $info;
    }

    /**
     * @param $callMode
     * @return mixed|string
     * @throws CookieExpiredException
     * @throws CookieInvalidException
     */
    public function tryParseCookie($callMode) {
        $parsedCookie = Cookie::get($this->info[CookieManager::COOKIE_NAME]);

        if (isset($parsedCookie)) {
            $parsedCookie = json_decode($parsedCookie, true);
            $this->validateCookie($parsedCookie);
        }
        else {
            if ($callMode !== ProxyAux::MODE_LOGIN) {
                throw new CookieExpiredException();
            }
        }

        return $parsedCookie;
    }

    /**
     * @param array $content
     * @return mixed
     */
    public function createCookie(Array $content) {
        if (!isset($this->info[CookieManager::COOKIE_TIME]) || $this->info[CookieManager::COOKIE_TIME] == null) {
            $cookie = Cookie::forever($this->info[CookieManager::COOKIE_NAME], json_encode($content));
        } else {
            $cookie = Cookie::make($this->info[CookieManager::COOKIE_NAME], json_encode($content), $this->info[CookieManager::COOKIE_TIME]);
        }

        return $cookie;
    }

    /**
     * @return mixed
     */
    public function destroyCookie() {
        return Cookie::forget($this->info[CookieManager::COOKIE_NAME]);
    }

    /**
     * @param $parsedCookie
     * @return bool
     * @throws CookieInvalidException
     */
    public function validateCookie($parsedCookie) {
        if (!isset($parsedCookie) || !array_key_exists(ProxyAux::ACCESS_TOKEN, $parsedCookie)) {
            throw new CookieInvalidException(ProxyAux::ACCESS_TOKEN);
        }
        if (!array_key_exists(ProxyAux::TOKEN_TYPE, $parsedCookie)) {
            throw new CookieInvalidException(ProxyAux::TOKEN_TYPE);
        }
        if (!array_key_exists(ProxyAux::TOKEN_EXPIRES, $parsedCookie)) {
            throw new CookieInvalidException(ProxyAux::TOKEN_EXPIRES);
        }
        if (!array_key_exists(ProxyAux::COOKIE_URI, $parsedCookie)) {
            throw new CookieInvalidException(ProxyAux::COOKIE_URI);
        }
        if (!array_key_exists(ProxyAux::CLIENT_ID, $parsedCookie)) {
            throw new CookieInvalidException(ProxyAux::CLIENT_ID);
        }

        return true;
    }

}
