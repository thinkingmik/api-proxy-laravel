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
use Andreoli\ApiProxy\Exceptions\ProxyMissingParamException;
use Andreoli\ApiProxy\Managers\CookieManager;
use Andreoli\ApiProxy\Managers\RequestManager;
use Illuminate\Http\Response;

class Proxy {

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

    /**
     * @param $method
     * @param array $inputs
     * @return Response
     * @throws ProxyMissingParamException
     */
    public function makeRequest($method, Array $inputs) {
        $this->checkMandatoriesInputParams($inputs);
        $this->uri = trim(urldecode($inputs[$this->uriParam]));

        //Retrieve the call mode from input parameters
        $this->callMode = $this->getRequestMode($inputs);

        //Remove unuseful parameters from inputs
        $inputs = ProxyAux::removeQueryValue($inputs, $this->uriParam);
        $inputs = ProxyAux::removeQueryValue($inputs, $this->skipParam);

        //Read the cookie if exists
        $parsedCookie = null;
        if ($this->callMode !== ProxyAux::MODE_SKIP) {
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
        $requestManager  = new RequestManager($this->uri, $method, $this->clientSecrets, $this->callMode, $this->cookieManager);
        $proxyResponse = $requestManager->executeRequest($inputs, $parsedCookie);

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
        $grantType = ProxyAux::getQueryValue($inputs, ProxyAux::GRANT_TYPE);
        $skip = ProxyAux::getQueryValue($inputs, $this->skipParam);
        $mode = ProxyAux::MODE_TOKEN;

        if (isset($grantType)) {
            if ($grantType === ProxyAux::PASSWORD_GRANT) {
                $mode = ProxyAux::MODE_LOGIN;
            }
        }
        else if (isset($skip) && strtolower($skip) === 'true') {
            $mode = ProxyAux::MODE_SKIP;
        }

        return $mode;
    }

    /**
     * @param $proxyResponse
     * @param $cookie
     * @return Response
     */
    private function setApiResponse($proxyResponse, $cookie) {
        $response = new Response($proxyResponse->getContent(), $proxyResponse->getStatusCode());

        if ($this->callMode === ProxyAux::MODE_LOGIN) {
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