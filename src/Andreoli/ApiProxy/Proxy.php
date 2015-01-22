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
use Andreoli\ApiProxy\Models\ProxyResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;

class Proxy {

    private $loginCall = false;
    private $uriParam = null;
    private $grantTypeParam = null;
    private $clientIdParam = null;
    private $clientSecretParam = null;
    private $accessTokenParam = null;
    private $clientSecrets = null;
    private $cookieInfo = array();

    public function __construct($params) {
        $this->uriParam = $params['uri_param'];
        $this->grantTypeParam = $params['grant_type_param'];
        $this->clientIdParam = $params['client_id_param'];
        $this->clientSecretParam = $params['client_secret_param'];
        $this->accessTokenParam = $params['access_token_param'];
        $this->clientSecrets = $params['client_secrets'];
        $this->cookieInfo = $params['cookie_info'];
    }

    /**
     * @param $method
     * @param array $inputs
     * @return Response
     * @throws MissingClientSecretException
     * @throws ProxyMissingParamException
     */
    public function makeRequest($method, Array $inputs) {

        //Remove the url parameter from input and store it in a variable
        $this->checkInputParams($inputs);
        $uriVal = trim(urldecode($inputs[$this->uriParam]));
        $indexSpam = array_search($inputs[$this->uriParam], $inputs);
        unset($inputs[$indexSpam]);

        $parsedCookie = Cookie::get($this->cookieInfo['name']);
        //Log::info(var_export($parsedCookie, true));
        if (isset($parsedCookie)) {
            $parsedCookie = json_decode($parsedCookie, true);
        }

        //If it is a login call add client secret else add access token read from cookie
        $inputs = $this->changeInputParameters($inputs, $parsedCookie);

        //Send HTTP request
        $guzzleResponse = $this->sendRequest($method, $uriVal, $inputs);
        $proxyResponse = new ProxyResponse($guzzleResponse->getStatusCode(), $guzzleResponse->getReasonPhrase(), $guzzleResponse->getProtocolVersion(), $this->getResponseContent($guzzleResponse));
        $response = new Response($proxyResponse->getContent(), $proxyResponse->getStatusCode());

        if ($this->loginCall) {
            if (isset($parsedCookie)) {
                Cookie::forget($this->cookieInfo['name']);
            }
            if (!isset($this->cookieInfo['time']) || $this->cookieInfo['time'] == null) {
                $cookie = Cookie::forever($this->cookieInfo['name'], json_encode($proxyResponse->getContent()));
            } else {
                $cookie = Cookie::make($this->cookieInfo['name'], json_encode($proxyResponse->getContent()), $this->cookieInfo['time']);
            }
            $response->withCookie($cookie);
            //Log::info(var_export($cookie, true));
        }

        return $response;
    }

    /**
     * @param $inputs
     * @param $cookie
     * @return array
     * @throws MissingClientSecretException
     */
    private function changeInputParameters($inputs, $cookie) {
        $newInputs = null;
        if ($this->loginCall) {
            $secret = $this->getClientSecret($inputs[$this->clientIdParam]);
            if (array_key_exists($this->clientSecretParam, $inputs)) {
                unset($inputs[$this->clientSecretParam]);
            }
            $newInputs = array_add($inputs, $this->clientSecretParam, $secret);
        }
        else {
            if (array_key_exists($this->accessTokenParam, $inputs)) {
                unset($inputs[$this->accessTokenParam]);
            }
            $newInputs = array_add($inputs, $this->accessTokenParam, $cookie['access_token']);
        }

        return $newInputs;
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

        //Check if call is a login request
        if (array_key_exists($this->grantTypeParam, $inputs) && strtolower($inputs[$this->grantTypeParam]) === 'password') {
            $this->loginCall = true;
        }
    }

}