<?php

/**
 * @package   andreoli/api-proxy-laravel
 * @author    Michele Andreoli <michi.andreoli[at]gmail.com>
 * @copyright Copyright (c) Michele Andreoli
 * @license   http://mit-license.org/
 * @link      https://github.com/mandreoli/api-proxy-laravel
 */

namespace Andreoli\ApiProxy;

use Andreoli\ApiProxy\Exceptions\CookieExpiredException;
use Andreoli\ApiProxy\Exceptions\MissingClientSecretException;
use Andreoli\ApiProxy\Exceptions\ProxyException;
use Andreoli\ApiProxy\Exceptions\ProxyMissingParamException;
use Andreoli\ApiProxy\Models\ProxyResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;

class Proxy {

    const GRANT_TYPE = 'grant_type';
    const ACCESS_TOKEN = 'access_token';
    const REFRESH_TOKEN = 'refresh_token';
    const CLIENT_ID = 'client_id';
    const CLIENT_SECRET = 'client_secret';
    const URI = 'uri';

    private $loginCall = false;
    private $reqAccessToken = null;
    private $uriParam = null;
    private $grantTypeParam = null;
    private $clientIdParam = null;
    private $clientSecretParam = null;
    private $accessTokenParam = null;
    private $refreshTokenParam = null;
    private $clientSecrets = null;
    private $cookieInfo = array();

    public function __construct($params) {
        $this->uriParam = $params['uri_param'];
        $this->reqAccessToken = $params['req_access_token'];
        $this->grantTypeParam = Proxy::GRANT_TYPE;
        $this->clientIdParam = Proxy::CLIENT_ID;
        $this->clientSecretParam = Proxy::CLIENT_SECRET;
        $this->accessTokenParam = Proxy::ACCESS_TOKEN;
        $this->refreshTokenParam = Proxy::REFRESH_TOKEN;
        $this->clientSecrets = $params['client_secrets'];
        $this->cookieInfo = $params['cookie_info'];
    }

    public function makeRequest($method, Array $inputs) {

        $this->checkInputParams($inputs);
        $uriVal = trim(urldecode($inputs[$this->uriParam]));

        //Remove unuseful parameters from inputs
        $inputs = $this->removeQueryValue($inputs, $this->uriParam);
        $inputs = $this->removeQueryValue($inputs, $this->reqAccessToken);

        //Read cookie if exists
        $parsedCookie = $this->tryParseCookie();

        //Send first HTTP request
        $proxyResponse = $this->replicateRequest($method, $uriVal, $inputs, $parsedCookie, false);

        //Create cookie if not exists
        $cookie = null;
        if ($this->loginCall) {
            $cookie = $this->createCookie($proxyResponse, $uriVal, $inputs[$this->clientIdParam]);
        }
        else {
            if ($proxyResponse->getStatusCode() !== 200 && array_key_exists(Proxy::REFRESH_TOKEN, $parsedCookie)) {
                $oldInputs = $inputs;
                $proxyResponse = $this->replicateRequest($method, $parsedCookie[Proxy::URI], $inputs, $parsedCookie, true);

                if ($proxyResponse->getStatusCode() === 200) {
                    $cookie = $this->createCookie($proxyResponse, $parsedCookie[Proxy::URI], $parsedCookie[Proxy::CLIENT_ID]);
                   ///TODO: update access token
                    // $inputs = $oldInputs;
                    //$inputs[Proxy::ACCESS_TOKEN] = $proxyResponse->getContent()[Proxy::ACCESS_TOKEN];
                    $proxyResponse = $this->replicateRequest($method, $uriVal, $inputs, $parsedCookie, false);
                }
                else {
                    $this->destroyCookie();
                }
            }
        }

        return $this->setApiResponse($proxyResponse, $cookie);
    }

    /**
     * @param $proxyResponse
     * @param Cookie $cookie
     * @return Response
     */
    private function setApiResponse($proxyResponse, $cookie) {
        $response = new Response($proxyResponse->getContent(), $proxyResponse->getStatusCode());
        if ($this->loginCall) {
            $response->setContent('');
            $response->withCookie($cookie);
        }

        return $response;
    }

    /**
     * @param ProxyResponse $proxyResponse
     * @param $uriVal
     * @param $clientId
     * @return mixed
     */
    private function createCookie(ProxyResponse $proxyResponse, $uriVal, $clientId) {
        $this->destroyCookie();

        $content = array_add($proxyResponse->getContent(), Proxy::CLIENT_ID, $clientId);
        $content = array_add($content, Proxy::URI, $uriVal);

        if (!isset($this->cookieInfo['time']) || $this->cookieInfo['time'] == null) {
            $cookie = Cookie::forever($this->cookieInfo['name'], json_encode($content));
        } else {
            $cookie = Cookie::make($this->cookieInfo['name'], json_encode($content), $this->cookieInfo['time']);
        }

        return $cookie;
    }

    /**
     * @return mixed|null
     * @throws CookieExpiredException
     */
    private function tryParseCookie() {
        $parsedCookie = Cookie::get($this->cookieInfo['name']);
        if (isset($parsedCookie)) {
            $parsedCookie = json_decode($parsedCookie, true);
        }
        else {
            if (!$this->loginCall) {
                throw new CookieExpiredException();
            }
        }

        return $parsedCookie;
    }

    public function destroyCookie() {
        Cookie::forget($this->cookieInfo['name']);
    }

    /**
     * @param $inputs
     * @param $parsedCookie
     * @param $toRefresh
     * @return array
     * @throws MissingClientSecretException
     */
    private function changeInputParameters($inputs, $parsedCookie, $toRefresh) {
        $refresh = (array_key_exists(Proxy::REFRESH_TOKEN, $parsedCookie)) ? $parsedCookie[Proxy::REFRESH_TOKEN] : null;

        if (isset($refresh) && $toRefresh) {
            //Add grant type value
            $inputs = $this->addQueryValue($inputs, $this->grantTypeParam, 'refresh_token');
            //Add refresh token value
            $inputs = $this->addQueryValue($inputs, $this->refreshTokenParam, $refresh);
            //Add client ID value
            $inputs = $this->addQueryValue($inputs, $this->clientIdParam, $parsedCookie[Proxy::CLIENT_ID]);
        }

        if ($this->loginCall || $toRefresh) {
            //Get client secret key
            $secret = $this->getClientSecret($inputs[$this->clientIdParam]);
            //Add client secret value
            $inputs = $this->addQueryValue($inputs, $this->clientSecretParam, $secret);
        }
        else {
            //Add access token value
            $inputs = $this->addQueryValue($inputs, $this->accessTokenParam, $parsedCookie[Proxy::ACCESS_TOKEN]);
        }

        return $inputs;
    }

    /**
     * @param $method
     * @param $uriVal
     * @param $inputs
     * @return \GuzzleHttp\Message\FutureResponse|\GuzzleHttp\Message\ResponseInterface|\GuzzleHttp\Ring\Future\FutureInterface|mixed|null
     */
    private function sendGuzzleRequest($method, $uriVal, $inputs) {
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
     * @param $method
     * @param $uriVal
     * @param $inputs
     * @param $parsedCookie
     * @param $toRefresh
     * @return ProxyResponse
     */
    private function replicateRequest($method, $uriVal, $inputs, $parsedCookie, $toRefresh) {
        //If it is a login call add client secret else add access token read from cookie
        $inputs = $this->changeInputParameters($inputs, $parsedCookie, $toRefresh);

        Log::info(var_export($uriVal, true));
        Log::info(var_export($inputs, true));
        $guzzleResponse = $this->sendGuzzleRequest($method, $uriVal, $inputs);
        $client = (isset($parsedCookie)) ? $parsedCookie[Proxy::CLIENT_ID] : null;
        Log::info(var_export($this->getResponseContent($guzzleResponse), true));

        $proxyResponse = new ProxyResponse($client, $guzzleResponse->getStatusCode(), $guzzleResponse->getReasonPhrase(), $guzzleResponse->getProtocolVersion(), $this->getResponseContent($guzzleResponse));

        return $proxyResponse;
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

        //Set if request is a login call
        if (array_key_exists($this->reqAccessToken, $inputs)) {
            if (strtolower($inputs[$this->reqAccessToken]) == 'true'  || strtolower($inputs[$this->reqAccessToken]) == 'y') {
                $this->loginCall = true;
            }
        }
    }

    /**
     * @param $array
     * @param $key
     * @param $value
     * @return array
     */
    private function addQueryValue($array, $key, $value) {
        if (array_key_exists($key, $array)) {
            unset($array[$key]);
        }
        return array_add($array, $key, $value);
    }

    /**
     * @param $array
     * @param $key
     * @return array
     */
    private function removeQueryValue($array, $key) {
        if (array_key_exists($key, $array)) {
            unset($array[$key]);
        }
        return $array;
    }

}