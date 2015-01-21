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
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class Proxy {

    private $loginCall = false;
    private $uriParam = null;
    private $loginParams = array();
    private $clientSecrets = null;

    public function __construct($params) {
        $this->uriParam = $params['uri_param'];
        $this->loginParams = array(
            'clientId' => $params['login_client_id_param'],
            'clientSecret' => $params['login_client_secret_param'],
            'username' => $params['login_username_param'],
            'password' => $params['login_password_param']
        );
        $this->clientSecrets = $params['client_secrets'];
    }


    /**
     * @param $header
     * @param $method
     * @param array $inputs
     * @return \GuzzleHttp\Stream\StreamInterface|null
     * @throws MissingClientSecretException
     * @throws ProxyMissingParamException
     */
    public function makeRequest($header, $method, Array $inputs) {
        $this->checkInputParams($inputs);

        $uriVal = trim(urldecode($inputs[$this->uriParam]));

        //If it is a login call add client secret
        if ($this->loginCall) {
            //Add client secret
            $secret = $this->getClientSecret($inputs[$this->loginParams['clientId']]);
            $inputs = array_add($inputs, $this->loginParams['clientSecret'], $secret);
        }

        //Remove the url parameter from input
        $indexSpam = array_search($inputs[$this->uriParam], $inputs);
        unset($inputs[$indexSpam]);

        //Make HTTP request
        $response = $this->sendRequest($method, $uriVal, $inputs);

        //Get response
        //TODO: create a model for this response
        return array(
            'content'   => $this->getResponseContent($response),
            'status'    => $response->getStatusCode()
        );
    }

    /**
     * @param $method
     * @param $uriVal
     * @param $inputs
     * @return \GuzzleHttp\Message\FutureResponse|\GuzzleHttp\Message\ResponseInterface|\GuzzleHttp\Ring\Future\FutureInterface|mixed|null
     */
    private function sendRequest($method, $uriVal, $inputs) {
        $options = array();
        $client = new Client();
        if ($method === 'GET') {
            $options = array_add($options, 'query', $inputs);
        }
        else {
            $options = array_add($options, 'body', $inputs);
        }
        $request = $client->createRequest($method, $uriVal, $options);

        try {
            $response = $client->send($request);
        }
        catch (ClientException $ex) {
            $response = $ex->getResponse();
        }

        return $response;
    }

    /**
     * @param $response
     * @return mixed
     */
    private function getResponseContent($response) {
        switch ($response->getHeader('content-type')) {
            case 'application/json':
                return $response->json();
            case 'text/xml':
            case 'application/xml':
                return $response->xml();
            default:
                return $response->getBody();
        }
    }

    /**
     * @param $clientId
     * @return mixed
     * @throws MissingClientSecretException
     */
    private function getClientSecret($clientId) {
        if (!array_key_exists($clientId, $this->clientSecrets)) {
            throw new MissingClientSecretException($clientId);
        }

        return $this->clientSecrets[$clientId];
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
        if (!array_key_exists($this->loginParams['username'], $inputs) && !array_key_exists($this->loginParams['password'], $inputs) && !array_key_exists($this->loginParams['clientId'], $inputs)) {
            $this->loginCall = false;
        }
        else {
            foreach ($this->loginParams as $key => $value) {
                if (!array_key_exists($value, $inputs) && $value != $this->loginParams['clientSecret']) {
                    throw new ProxyMissingParamException($value);
                }
            }
            $this->loginCall = true;
        }
    }

}