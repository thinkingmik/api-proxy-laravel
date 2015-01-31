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
use Andreoli\ApiProxy\Models\CookieManager;
use Andreoli\ApiProxy\Models\ProxyResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class Proxy {

    const GRANT_TYPE = 'grant_type';
    const ACCESS_TOKEN = 'access_token';
    const TOKEN_TYPE = 'token_type';
    const TOKEN_EXPIRES = 'expires_in';
    const REFRESH_TOKEN = 'refresh_token';
    const CLIENT_ID = 'client_id';
    const CLIENT_SECRET = 'client_secret';
    const COOKIE_URI = 'uri';
    const PASSWORD_GRANT = 'password';
    const MODE_SKIP = '0';
    const MODE_LOGIN = '1';
    const MODE_TOKEN = '2';

    private $uri = null;
    private $callMode = null;
    private $uriParam = null;
    private $skipParam = null;
    private $redirectUri = null;
    private $clientSecrets = null;
    private $cookieManager = null;

    public function __construct($params) {
        $this->uriParam = $params['uri_param'];
        $this->skipParam = $params['skip_param'];
        $this->redirectUri = $params['redirect_login'];
        $this->clientSecrets = $params['client_secrets'];
        $this->cookieManager = new CookieManager($params['cookie_info']);
    }

    public function makeRequest($method, Array $inputs) {
        $this->checkMandatoriesInputParams($inputs);
        $this->uri = trim(urldecode($inputs[$this->uriParam]));

        //Retrieve the call mode from input parameters
        $this->callMode = $this->getRequestMode($inputs);

        //Remove unuseful parameters from inputs
        $inputs = $this->removeQueryValue($inputs, $this->uriParam);
        $inputs = $this->removeQueryValue($inputs, $this->skipParam);

        //Read the cookie if exists
        $parsedCookie = null;
        if ($this->callMode !== Proxy::MODE_SKIP) {
            try {
                $parsedCookie = $this->cookieManager->tryParseCookie($this->callMode);
            } catch (CookieExpiredException $ex) {
                if (isset($this->redirectUri) && !empty($this->redirectUri)) {
                    return \Redirect::to($this->redirectUri);
                }
                throw new $ex;
            }
        }

        //Create the new request
        $proxyResponse = $this->executeRequest($method, $inputs, $parsedCookie);

        return $this->setApiResponse($proxyResponse['response'], $proxyResponse['cookie']);
    }

    /**
     * @param array $inputs
     * @throws ProxyMissingParamException
     */
    private function checkMandatoriesInputParams(Array $inputs) {
        //Check if URI param exists in the input data array
        if (!array_key_exists($this->uriParam, $inputs)) {
            throw new ProxyMissingParamException($this->uriParam);
        }
    }

    /**
     * @param $inputs
     * @return string
     */
    private function getRequestMode($inputs) {
        $grantType = $this->getQueryValue($inputs, Proxy::GRANT_TYPE);
        $skip = $this->getQueryValue($inputs, $this->skipParam);
        $mode = Proxy::MODE_TOKEN;

        if (isset($grantType)) {
            if ($grantType === Proxy::PASSWORD_GRANT) {
                $mode = Proxy::MODE_LOGIN;
            }
        }
        else if (isset($skip) && strtolower($skip) === 'true') {
            $mode = Proxy::MODE_SKIP;
        }

        return $mode;
    }

    /**
     * @param $array
     * @param $key
     * @return null
     */
    private function getQueryValue($array, $key) {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }
        return "";
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

    /**
     * @param $method
     * @param $inputs
     * @param $parsedCookie
     * @return array
     */
    private function executeRequest($method, $inputs, $parsedCookie) {
        $cookie = null;
        switch ($this->callMode) {
            case Proxy::MODE_LOGIN:
                $inputs = $this->addLoginExtraParams($inputs);
                $proxyResponse = $this->replicateRequest($method, $this->uri, $inputs);

                $clientId = (array_key_exists(Proxy::CLIENT_ID, $inputs)) ? $inputs[Proxy::CLIENT_ID] : null;
                $content = $proxyResponse->getContent();
                $content = $this->addQueryValue($content, Proxy::COOKIE_URI, $this->uri);
                $content = $this->addQueryValue($content, Proxy::CLIENT_ID, $clientId);

                $cookie = $this->cookieManager->createCookie($content);
                break;
            case Proxy::MODE_TOKEN:
                $inputs = $this->addTokenExtraParams($inputs, $parsedCookie);
                $proxyResponse = $this->replicateRequest($method, $this->uri, $inputs);

                //Get a new access token from refresh token if exists
                if ($proxyResponse->getStatusCode() != 200 && array_key_exists(Proxy::REFRESH_TOKEN, $parsedCookie)) {
                    //Get a new access token from refresh token
                    $inputs = $this->removeTokenExtraParams($inputs);
                    $inputs = $this->addRefreshExtraParams($inputs, $parsedCookie);
                    $proxyResponse = $this->replicateRequest($method, $parsedCookie[Proxy::COOKIE_URI], $inputs);

                    $content = $proxyResponse->getContent();
                    if ($proxyResponse->getStatusCode() === 200 && array_key_exists(Proxy::ACCESS_TOKEN, $content)) {
                        $parsedCookie[Proxy::ACCESS_TOKEN] = $content[Proxy::ACCESS_TOKEN];
                        $parsedCookie[Proxy::REFRESH_TOKEN] = $content[Proxy::REFRESH_TOKEN];

                        $inputs = $this->removeRefreshTokenExtraParams($inputs);
                        $inputs = $this->addTokenExtraParams($inputs, $parsedCookie);
                        $proxyResponse = $this->replicateRequest($method, $this->uri, $inputs);

                        //Set a new cookie with updated access token and refresh token
                        $cookie = $this->cookieManager->createCookie($parsedCookie);
                    }
                }
                break;
            default:
                $proxyResponse = $this->replicateRequest($method, $this->uri, $inputs);
        }

        return array(
            'response' => $proxyResponse,
            'cookie' => $cookie
        );
    }

    /**
     * @param $inputs
     * @return array
     */
    private function addLoginExtraParams($inputs) {
        //Get client secret key
        $clientId = (array_key_exists(Proxy::CLIENT_ID, $inputs)) ? $inputs[Proxy::CLIENT_ID] : null;
        $clientInfo = $this->getClientInfo($clientId);

        if (isset($clientInfo['id'])) {
            $inputs = $this->addQueryValue($inputs, Proxy::CLIENT_ID, $clientInfo['id']);
        }
        if (isset($clientInfo['secret'])) {
            $inputs = $this->addQueryValue($inputs, Proxy::CLIENT_SECRET, $clientInfo['secret']);
        }

        return $inputs;
    }

    /**
     * @param $clientId
     * @return array
     * @throws MissingClientSecretException
     */
    private function getClientInfo($clientId) {
        $info = ['id' => null, 'secret' => null];

        if (isset($clientId)) {
            if (!array_key_exists($clientId, $this->clientSecrets)) {
                throw new MissingClientSecretException($clientId);
            }
            $info['id'] = $clientId;
            $info['secret'] = $this->clientSecrets[$clientId];
        }
        else if (count($this->clientSecrets) >= 1) {
            $firstKey = key($this->clientSecrets);
            $info['id'] = $firstKey;
            $info['secret'] = $this->clientSecrets[$firstKey];
        }

        return $info;
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

    private function replicateRequest($method, $uri, $inputs) {
        Log::info('<----------------------- BEGIN REQUEST ----------------------------------');
        Log::info(var_export($uri, true));
        Log::info(var_export($inputs, true));
        Log::info('----------------------- END REQUEST ------------------------------------>');
        $guzzleResponse = $this->sendGuzzleRequest($method, $uri, $inputs);
        Log::info('<----------------------- BEGIN RESPONSE ----------------------------------');
        Log::info(var_export($this->getResponseContent($guzzleResponse), true));
        Log::info('----------------------- END RESPONSE ------------------------------------>');
        Log::info('*************************************************************************');

        $proxyResponse = new ProxyResponse(null, $guzzleResponse->getStatusCode(), $guzzleResponse->getReasonPhrase(), $guzzleResponse->getProtocolVersion(), $this->getResponseContent($guzzleResponse));

        return $proxyResponse;
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
     * @param $inputs
     * @param $parsedCookie
     * @return array
     */
    private function addTokenExtraParams($inputs, $parsedCookie) {
        if (isset($parsedCookie[Proxy::ACCESS_TOKEN])) {
            $inputs = $this->addQueryValue($inputs, Proxy::ACCESS_TOKEN, $parsedCookie[Proxy::ACCESS_TOKEN]);
        }

        return $inputs;
    }

    /**
     * @param $inputs
     * @param $parsedCookie
     * @return array
     */
    private function addRefreshExtraParams($inputs, $parsedCookie) {
        $inputs = $this->addQueryValue($inputs, Proxy::GRANT_TYPE, Proxy::REFRESH_TOKEN);
        $inputs = $this->addQueryValue($inputs, Proxy::REFRESH_TOKEN, $parsedCookie[Proxy::REFRESH_TOKEN]);
        if (isset($parsedCookie[Proxy::CLIENT_ID])) {
            $clientInfo = $this->getClientInfo($parsedCookie[Proxy::CLIENT_ID]);
            if (isset($clientInfo['id'])) {
                $inputs = $this->addQueryValue($inputs, Proxy::CLIENT_ID, $clientInfo['id']);
            }
            if (isset($clientInfo['secret'])) {
                $inputs = $this->addQueryValue($inputs, Proxy::CLIENT_SECRET, $clientInfo['secret']);
            }
        }

        return $inputs;
    }

    /**
     * @param $inputs
     * @return array
     */
    private function removeTokenExtraParams($inputs) {
        $inputs = $this->removeQueryValue($inputs, Proxy::ACCESS_TOKEN);

        return $inputs;
    }

    /**
     * @param $inputs
     * @return array
     */
    private function removeRefreshTokenExtraParams($inputs) {
        $inputs = $this->removeQueryValue($inputs, Proxy::GRANT_TYPE);
        $inputs = $this->removeQueryValue($inputs, Proxy::REFRESH_TOKEN);
        $inputs = $this->removeQueryValue($inputs, Proxy::CLIENT_ID);
        $inputs = $this->removeQueryValue($inputs, Proxy::CLIENT_SECRET);

        return $inputs;
    }

    /**
     * @param $proxyResponse
     * @param $cookie
     * @return Response
     */
    private function setApiResponse($proxyResponse, $cookie) {
        $response = new Response($proxyResponse->getContent(), $proxyResponse->getStatusCode());

        if ($this->callMode === Proxy::MODE_LOGIN) {
            $response->setContent(json_encode($this->successAccessToken()));
        }
        if (isset($cookie)) {
            $response->withCookie($cookie);
        }

        return $response;
    }

    /**
     * @return array
     */
    private function successAccessToken() {
        return array(
            'success_code' => 'access_token_ok',
            'success_message' => \Lang::get('api-proxy-laravel::messages.access_token_ok')
        );
    }

}