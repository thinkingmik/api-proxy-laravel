<?php

/**
 * @package   thinkingmik/api-proxy-laravel
 * @author    Michele Andreoli <michi.andreoli[at]gmail.com>
 * @copyright Copyright (c) Michele Andreoli
 * @license   http://mit-license.org/
 * @link      https://github.com/thinkingmik/api-proxy-laravel
 */

namespace ThinKingMik\ApiProxy\Managers;

use ThinKingMik\ApiProxy\ProxyAux;
use ThinKingMik\ApiProxy\Models\ProxyResponse;
use ThinKingMik\ApiProxy\Models\MixResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use ThinKingMik\ApiProxy\Exceptions\MissingClientSecretException;

class RequestManager {

    private $uri = null;
    private $method = null;
    private $callMode = null;
    private $clientSecrets = null;
    private $cookieManager = null;
    private $useHeader = false;

    public function __construct($uri, $method, $clientSecrets, $callMode, $cookieManager) {
        $this->uri = $uri;
        $this->method = $method;
        $this->clientSecrets = $clientSecrets;
        $this->callMode = $callMode;
        $this->cookieManager = $cookieManager;
    }

    public function enableHeader() {
        $this->useHeader = true;
    }

    /**
     * @param $inputs
     * @param $parsedCookie
     * @return array
     */
    public function executeRequest($inputs, $parsedCookie) {
        switch ($this->callMode) {
            case ProxyAux::MODE_LOGIN:
                $mixed = $this->execAccess($inputs);
                break;
            case ProxyAux::MODE_TOKEN:
                $mixed = $this->execRefresh($inputs, $parsedCookie);
                break;
            case ProxyAux::MODE_REVOKE:
                $mixed = $this->execRevoke($inputs, $parsedCookie);
                break;
            default:
                $proxyResponse = $this->replicateRequest($this->method, $this->uri, $inputs);
                $mixed = new MixResponse($proxyResponse, null);
        }

        return $mixed;
    }

    /**
     * @param array $inputs
     * @return MixResponse
     */
    private function execAccess(Array $inputs) {
        $cookie = null;
        $inputs = $this->addLoginExtraParams($inputs);
        $proxyResponse = $this->replicateRequest($this->method, $this->uri, $inputs);

        if ($proxyResponse->getStatusCode() === 200) {
            $clientId = (array_key_exists(ProxyAux::CLIENT_ID, $inputs)) ? $inputs[ProxyAux::CLIENT_ID] : null;
            $content = $proxyResponse->getContent();
            $content = ProxyAux::addQueryValue($content, ProxyAux::COOKIE_URI, $this->uri);
            $content = ProxyAux::addQueryValue($content, ProxyAux::COOKIE_METHOD, $this->method);
            $content = ProxyAux::addQueryValue($content, ProxyAux::CLIENT_ID, $clientId);

            $cookie = $this->cookieManager->createCookie($content);
        }

        $mixed = new MixResponse($proxyResponse, $cookie);
        return $mixed;
    }

    /**
     * @param array $inputs
     * @param $parsedCookie
     * @return MixResponse
     */
    private function execRefresh(Array $inputs, $parsedCookie) {
        $inputs = $this->addTokenExtraParams($inputs, $parsedCookie);
        $proxyResponse = $this->replicateRequest($this->method, $this->uri, $inputs);

        //Get a new access token from refresh token if exists
        $cookie = null;
        $mixed = new MixResponse($proxyResponse, $cookie);

        if ($proxyResponse->getStatusCode() != 200) {
            if (array_key_exists(ProxyAux::REFRESH_TOKEN, $parsedCookie)) {
                $mixed = $this->tryRefreshToken($inputs, $parsedCookie);
            }
            else {
                $cookie = $this->cookieManager->destroyCookie();
                $mixed->setCookie($cookie);
            }
        }

        return $mixed;
    }

    /**
     * @param array $inputs
     * @param $parsedCookie
     * @return MixResponse
     */
    private function execRevoke(Array $inputs, $parsedCookie) {
        $inputs = $this->addTokenExtraParams($inputs, $parsedCookie);
        $inputs = $this->addRevokeExtraParams($inputs, $parsedCookie);
        $proxyResponse = $this->replicateRequest($this->method, $this->uri, $inputs);

        $cookie = null;
        $mixed = new MixResponse($proxyResponse, $cookie);
        
        if ($proxyResponse->getStatusCode() === 200) {
	        if (array_key_exists(ProxyAux::REVOKE_TOKEN_TYPE_HINT, $inputs) && $inputs[ProxyAux::REVOKE_TOKEN_TYPE_HINT] == ProxyAux::REFRESH_TOKEN) {
	        	// Replace cookie without refresh token
	        	if (isset($parsedCookie[ProxyAux::REFRESH_TOKEN])) {
	        		unset($parsedCookie[ProxyAux::REFRESH_TOKEN]);
	        	}
	            $cookie = $this->cookieManager->createCookie($parsedCookie);
                $mixed->setCookie($cookie);
	        } else if (!array_key_exists(ProxyAux::REVOKE_TOKEN_TYPE_HINT, $inputs) || $inputs[ProxyAux::REVOKE_TOKEN_TYPE_HINT] == ProxyAux::ACCESS_TOKEN) {
	        	// Destroy cookie
                $cookie = $this->cookieManager->destroyCookie();
                $mixed->setCookie($cookie);
            }
        }
        
        return $mixed;
    }

    /**
     * @param $inputs
     * @param $parsedCookie
     * @return MixResponse
     */
    private function tryRefreshToken($inputs, $parsedCookie) {
        $this->callMode = ProxyAux::MODE_REFRESH;

        //Get a new access token from refresh token
        $inputs = $this->removeTokenExtraParams($inputs);
        $params = $this->addRefreshExtraParams(array(), $parsedCookie);
        $proxyResponse = $this->replicateRequest($parsedCookie[ProxyAux::COOKIE_METHOD], $parsedCookie[ProxyAux::COOKIE_URI], $params);

        $content = $proxyResponse->getContent();
        if ($proxyResponse->getStatusCode() === 200 && array_key_exists(ProxyAux::ACCESS_TOKEN, $content)) {
            $this->callMode = ProxyAux::MODE_TOKEN;
            $parsedCookie[ProxyAux::ACCESS_TOKEN] = $content[ProxyAux::ACCESS_TOKEN];
            $parsedCookie[ProxyAux::REFRESH_TOKEN] = $content[ProxyAux::REFRESH_TOKEN];

            $inputs = $this->addTokenExtraParams($inputs, $parsedCookie);
            $proxyResponse = $this->replicateRequest($this->method, $this->uri, $inputs);

            //Set a new cookie with updated access token and refresh token
            $cookie = $this->cookieManager->createCookie($parsedCookie);
        }
        else {
            $cookie = $this->cookieManager->destroyCookie();
        }

        $mixed = new MixResponse($proxyResponse, $cookie);

        return $mixed;
    }

    /**
     * @param $method
     * @param $uri
     * @param $inputs
     * @return ProxyResponse
     */
    private function replicateRequest($method, $uri, $inputs) {
        $guzzleResponse = $this->sendGuzzleRequest($method, $uri, $inputs);
        $proxyResponse = new ProxyResponse($guzzleResponse->getStatusCode(), $guzzleResponse->getReasonPhrase(), $guzzleResponse->getProtocolVersion(), $this->getResponseContent($guzzleResponse));

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
     * @param $method
     * @param $uriVal
     * @param $inputs
     * @return \GuzzleHttp\Message\FutureResponse|\GuzzleHttp\Message\ResponseInterface|\GuzzleHttp\Ring\Future\FutureInterface|mixed|null
     */
    private function sendGuzzleRequest($method, $uriVal, $inputs) {
        $options = array();
        $client = new Client();

        if ($this->callMode === ProxyAux::MODE_TOKEN && $this->useHeader === true) {
            $accessToken = ProxyAux::getQueryValue($inputs, ProxyAux::ACCESS_TOKEN);
            $inputs = ProxyAux::removeQueryValue($inputs, ProxyAux::ACCESS_TOKEN);
            $options = array_add($options, 'headers', [ ProxyAux::HEADER_AUTH => 'Bearer ' . $accessToken ]);
        }

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
        catch (ServerException $ex) {
            $response = $ex->getResponse();
        }

        return $response;
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
     * @param $inputs
     * @return array
     */
    private function addLoginExtraParams($inputs) {
        //Get client secret key
        $clientId = (array_key_exists(ProxyAux::CLIENT_ID, $inputs)) ? $inputs[ProxyAux::CLIENT_ID] : null;
        $clientInfo = $this->getClientInfo($clientId);

        if (isset($clientInfo['id'])) {
            $inputs = ProxyAux::addQueryValue($inputs, ProxyAux::CLIENT_ID, $clientInfo['id']);
        }
        if (isset($clientInfo['secret'])) {
            $inputs = ProxyAux::addQueryValue($inputs, ProxyAux::CLIENT_SECRET, $clientInfo['secret']);
        }

        return $inputs;
    }

    /**
     * @param $inputs
     * @param $parsedCookie
     * @return array
     */
    private function addTokenExtraParams($inputs, $parsedCookie) {
        if (isset($parsedCookie[ProxyAux::ACCESS_TOKEN])) {
            $inputs = ProxyAux::addQueryValue($inputs, ProxyAux::ACCESS_TOKEN, $parsedCookie[ProxyAux::ACCESS_TOKEN]);
        }

        return $inputs;
    }

    /**
     * @param $inputs
     * @param $parsedCookie
     * @return array
     */
    private function addRefreshExtraParams($inputs, $parsedCookie) {
        $inputs = ProxyAux::addQueryValue($inputs, ProxyAux::GRANT_TYPE, ProxyAux::REFRESH_TOKEN);
        $inputs = ProxyAux::addQueryValue($inputs, ProxyAux::REFRESH_TOKEN, $parsedCookie[ProxyAux::REFRESH_TOKEN]);
        if (isset($parsedCookie[ProxyAux::CLIENT_ID])) {
            $clientInfo = $this->getClientInfo($parsedCookie[ProxyAux::CLIENT_ID]);
            if (isset($clientInfo['id'])) {
                $inputs = ProxyAux::addQueryValue($inputs, ProxyAux::CLIENT_ID, $clientInfo['id']);
            }
            if (isset($clientInfo['secret'])) {
                $inputs = ProxyAux::addQueryValue($inputs, ProxyAux::CLIENT_SECRET, $clientInfo['secret']);
            }
        }

        return $inputs;
    }

    /**
     * @param $inputs
     * @param $parsedCookie
     * @return array
     */
    private function addRevokeExtraParams($inputs, $parsedCookie) {
        if (array_key_exists(ProxyAux::REVOKE_TOKEN_TYPE_HINT, $inputs) && $inputs[ProxyAux::REVOKE_TOKEN_TYPE_HINT] == ProxyAux::REFRESH_TOKEN) {
        	if (isset($parsedCookie[ProxyAux::REFRESH_TOKEN])) {
            	$inputs = ProxyAux::addQueryValue($inputs, ProxyAux::REVOKE_TOKEN, $parsedCookie[ProxyAux::REFRESH_TOKEN]);
            }
        } else if (!array_key_exists(ProxyAux::REVOKE_TOKEN_TYPE_HINT, $inputs) || $inputs[ProxyAux::REVOKE_TOKEN_TYPE_HINT] == ProxyAux::ACCESS_TOKEN) {
        	if (isset($parsedCookie[ProxyAux::ACCESS_TOKEN])) {
            	$inputs = ProxyAux::addQueryValue($inputs, ProxyAux::REVOKE_TOKEN, $parsedCookie[ProxyAux::ACCESS_TOKEN]);
            }
        }

        return $inputs;
    }

    /**
     * @param $inputs
     * @return array
     */
    private function removeTokenExtraParams($inputs) {
        $inputs = ProxyAux::removeQueryValue($inputs, ProxyAux::ACCESS_TOKEN);

        return $inputs;
    }

}
