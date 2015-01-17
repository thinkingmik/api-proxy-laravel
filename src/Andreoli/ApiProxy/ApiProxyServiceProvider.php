<?php

/**
 * @package   andreoli/api-proxy-laravel
 * @author    Michele Andreoli <michi.andreoli[at]gmail.com>
 * @copyright Copyright (c) Michele Andreoli
 * @license   http://mit-license.org/
 * @link      https://github.com/mandreoli/api-proxy-laravel
 */

namespace Andreoli\ApiProxy;


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
        $this->package('andreoli/api-proxy-laravel');

        $this->bootFilters();
    }

    /**
     * Boot the filters
     * @return void
     */
    private function bootFilters() {

    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register() {
        $this->registerErrorHandlers();
        $this->registerApiProxy();
        $this->registerFilterBindings();
    }

    /**
     * Register ApiProxy with the IoC container
     * @return void
     */
    public function registerApiProxy() {
        $this->app->bindShared('api-proxy.proxy', function ($app) {
            $proxy = new Proxy();
            return $proxy;
        });

        $this->app->bind('Andreoli\ApiProxy\Proxy', function($app) {
            return $app['api-proxy.proxy'];
        });
    }

    /**
     * Register the Filters to the IoC container because some filters need additional parameters
     * @return void
     */
    public function registerFilterBindings() {

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

    }

}
