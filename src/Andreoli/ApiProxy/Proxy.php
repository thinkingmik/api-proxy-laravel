<?php

/**
 * @package   andreoli/api-proxy-laravel
 * @author    Michele Andreoli <michi.andreoli[at]gmail.com>
 * @copyright Copyright (c) Michele Andreoli
 * @license   http://mit-license.org/
 * @link      https://github.com/mandreoli/api-proxy-laravel
 */

namespace Andreoli\ApiProxy;

use Andreoli\ApiProxy\Exceptions\MissingClientSecretException;
use Andreoli\ApiProxy\Exceptions\ProxyMissingParamException;

class Proxy {

    private $loginCall = false;
    private $uriParam = null;
    private $loginParams = array();
    private $clientSecrets = null;

    public function __construct($params) {
        $this->uriParam = $params['uri_param'];
        $this->loginParams = array(
            'username' => $params['login_username_param'],
            'password' => $params['login_password_param'],
            'client' => $params['login_client_param']
        );
        $this->clientSecrets = $params['client_secrets'];
    }

    /**
     * @param array $inputs
     * @throws MissingClientSecretException
     * @throws ProxyMissingParamException
     */
    public function makeRequest(Array $inputs) {

        $this->checkInputParams($inputs);

        $uriVal = trim(urldecode($inputs[$this->uriParam]));

        //If it is a login call add client secret
        if ($this->loginCall) {
            //$usernameVal = $inputs[$this->loginParams['username']];
            //$passwordVal = $inputs[$this->loginParams['password']];
            $clientVal = $inputs[$this->loginParams['client']];

            if (!array_key_exists($clientVal, $this->clientSecrets)) {
                throw new MissingClientSecretException($clientVal);
            }

            //TODO: create a new request to uriVal
        }
    }

    /**
     * @param array $inputs
     * @throws ProxyMissingParamException
     */
    private function checkInputParams(Array $inputs) {
        //Check if URI param exists in the input data array
        if (!array_key_exists($this->uriParam, $inputs)) {
            throw new ProxyMissingParamException($this->uriParam);
        }
        //Check if login params exists
        if (!array_key_exists($this->loginParams['username'], $inputs) && !array_key_exists($this->loginParams['password'], $inputs) && !array_key_exists($this->loginParams['client'], $inputs)) {
            $this->loginCall = false;
        }
        else {
            foreach ($this->loginParams as $key => $value) {
                if (!array_key_exists($value, $inputs)) {
                    throw new ProxyMissingParamException($value);
                }
            }
            $this->loginCall = true;
        }
    }

}