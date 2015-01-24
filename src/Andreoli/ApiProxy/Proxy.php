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
    private $noCookie = false;
    private $requestMode = null;
    private $uriParam = null;
    private $redirectUri = null;
    private $grantTypeParam = null;
    private $clientIdParam = null;
    private $clientSecretParam = null;
    private $accessTokenParam = null;
    private $refreshTokenParam = null;
    private $clientSecrets = null;
    private $cookieInfo = array();

    public function __construct($params) {
        $this->uriParam = $params['uri_param'];
        $this->requestMode = $params['request_mode'];
        $this->redirectUri = $params['redirect_login'];
        $this->grantTypeParam = Proxy::GRANT_TYPE;
        $this->clientIdParam = Proxy::CLIENT_ID;
        $this->clientSecretParam = Proxy::CLIENT_SECRET;
        $this->accessTokenParam = Proxy::ACCESS_TOKEN;
        $this->refreshTokenParam = Proxy::REFRESH_TOKEN;
        $this->clientSecrets = $params['client_secrets'];
        $this->cookieInfo = $params['cookie_info'];
    }

    /**
     * @param $method
     * @param array $inputs
     * @return Response
     * @throws ProxyMissingParamException
     */
    public function makeRequest($method, Array $inputs) {

        $this->checkInputParams($inputs);
        $uriVal = trim(urldecode($inputs[$this->uriParam]));

        //Remove unuseful parameters from inputs
        $inputs = $this->removeQueryValue($inputs, $this->uriParam);
        $inputs = $this->removeQueryValue($inputs, $this->requestMode);

        //Read cookie if exists
        try {
            $parsedCookie = $this->tryParseCookie();
        }
        catch (CookieExpiredException $ex) {
            if (isset($this->redirectUri) && !empty($this->redirectUri)) {
                return \Redirect::to($this->redirectUri);
            }
            throw new $ex;
        }

        Log::info('-------------------- BEGIN COOKIE ----------------------');
        Log::info(var_export($parsedCookie, true));
        Log::info('-------------------- END COOKIE ------------------------');

        //Send first HTTP request
        $proxyResponse = $this->replicateRequest($method, $uriVal, $inputs, $parsedCookie, false);

        //Create cookie if not exists
        $cookie = null;
        if ($this->loginCall) {
            $clientId = (array_key_exists($this->clientIdParam, $inputs)) ? $inputs[$this->clientIdParam] : null;
            $cookie = $this->createCookie($proxyResponse, $uriVal, $clientId);
        }
        else if (!$this->noCookie) {
            if ($proxyResponse->getStatusCode() !== 200 && array_key_exists(Proxy::REFRESH_TOKEN, $parsedCookie)) {
                //Get a new access token from refresh token
                $proxyResponse = $this->replicateRequest($method, $parsedCookie[Proxy::URI], array(), $parsedCookie, true);

                $content = $proxyResponse->getContent();
                if ($proxyResponse->getStatusCode() === 200 && array_key_exists(Proxy::ACCESS_TOKEN, $content)) {
                    $parsedCookie[$this->accessTokenParam] = $content[$this->accessTokenParam];
                    $parsedCookie[$this->refreshTokenParam] = $content[$this->refreshTokenParam];
                    //Set a new cookie with updated access token and refresh token
                    $cookie = $this->createCookie($proxyResponse, $parsedCookie[Proxy::URI], $parsedCookie[Proxy::CLIENT_ID]);
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
        if ($this->loginCall || isset($cookie)) {
            if ($this->loginCall) {
                $response->setContent(json_encode($this->successAccessToken()));
            }
            $response->withCookie($cookie);
        }

        return $response;
    }

    private function successAccessToken() {
        return array(
            'success_code' => 'access_token_ok',
            'success_message' => \Lang::get('api-proxy-laravel::messages.access_token_ok')
        );
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
            if (!$this->loginCall && !$this->noCookie) {
                throw new CookieExpiredException();
            }
        }

        return $parsedCookie;
    }

    public function destroyCookie() {
        return Cookie::forget($this->cookieInfo['name']);
    }

    /**
     * @param $inputs
     * @param $parsedCookie
     * @param $toRefresh
     * @return array
     * @throws MissingClientSecretException
     */
    private function changeInputParameters($inputs, $parsedCookie, $toRefresh) {
        if ($this->noCookie) {
            return $inputs;
        }

        $refresh = (isset($parsedCookie) && array_key_exists(Proxy::REFRESH_TOKEN, $parsedCookie)) ? $parsedCookie[Proxy::REFRESH_TOKEN] : null;

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
            if (array_key_exists($this->clientIdParam, $inputs)) {
                $secret = $this->getClientSecret($inputs[$this->clientIdParam]);
                //Add client secret value
                $inputs = $this->addQueryValue($inputs, $this->clientSecretParam, $secret);
            }
        } else if (isset($parsedCookie)) {
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

        Log::info('<----------------------- BEGIN REQUEST ----------------------------------');
        Log::info(var_export($uriVal, true));
        Log::info(var_export($inputs, true));
        Log::info('----------------------- END REQUEST ------------------------------------>');
        $guzzleResponse = $this->sendGuzzleRequest($method, $uriVal, $inputs);
        $client = (isset($parsedCookie)) ? $parsedCookie[Proxy::CLIENT_ID] : null;
        Log::info('<----------------------- BEGIN RESPONSE ----------------------------------');
        Log::info(var_export($this->getResponseContent($guzzleResponse), true));
        Log::info('----------------------- END RESPONSE ------------------------------------>');
        Log::info('*************************************************************************');

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
        if (array_key_exists($this->requestMode, $inputs)) {
            if (strtolower($inputs[$this->requestMode]) == 'token') {
                $this->loginCall = true;
            }
            else if (strtolower($inputs[$this->requestMode]) == 'skip') {
                $this->noCookie = true;
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