<?php

/**
 * @package   thinkingmik/api-proxy-laravel
 * @author    Michele Andreoli <michi.andreoli[at]gmail.com>
 * @copyright Copyright (c) Michele Andreoli
 * @license   http://mit-license.org/
 * @link      https://github.com/thinkingmik/api-proxy-laravel
 */

namespace ThinKingMik\ApiProxy;

use Illuminate\Support\ServiceProvider;
use Illuminate\Http\JsonResponse;
use ThinKingMik\ApiProxy\Exceptions\ProxyException;

class ApiProxyServiceProvider extends ServiceProvider {

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot() {
        $this->package('thinkingmik/api-proxy-laravel');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register() {
        $this->registerErrorHandlers();
        $this->registerApiProxy();
    }

    /**
     * Register ApiProxy with the IoC container
     * @return void
     */
    public function registerApiProxy() {
        $this->app->bindShared('api-proxy.proxy', function ($app) {
            $params = $app['config']->get('api-proxy-laravel::proxy');
            $proxy = new Proxy($params);
            return $proxy;
        });

        $this->app->bind('ThinKingMik\ApiProxy\Proxy', function($app) {
            return $app['api-proxy.proxy'];
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides() {
        return array('api-proxy.proxy');
    }

    /**
     * Register the ApiProxy error handlers
     * @return void
     */
    private function registerErrorHandlers() {
        $this->app->error(function(ProxyException $ex) {
            if (\Request::ajax() && \Request::wantsJson()) {
                return new JsonResponse([
                    'error' => $ex->errorType,
                    'error_description' => $ex->getMessage()
                ], $ex->httpStatusCode, $ex->getHttpHeaders()
                );
            }

            return \View::make('api-proxy-laravel::proxy_error', array(
                'header' => $ex->getHttpHeaders()[0],
                'code' => $ex->httpStatusCode,
                'error' => $ex->errorType,
                'message' => $ex->getMessage()
            ));
        });
    }

}
