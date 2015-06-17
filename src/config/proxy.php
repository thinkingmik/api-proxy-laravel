<?php

/**
 * @package   thinkingmik/api-proxy-laravel
 * @author    Michele Andreoli <michi.andreoli[at]gmail.com>
 * @copyright Copyright (c) Michele Andreoli
 * @license   http://mit-license.org/
 * @link      https://github.com/thinkingmik/api-proxy-laravel
 */

return [

    /*
      |--------------------------------------------------------------------------
      | Proxy input: define the URI attribute name
      |--------------------------------------------------------------------------
      |
      | When you call the proxy helper, you need to pass the real URI of the API endpoint
      | in this parameter. If the URI is encoded the proxy will decode it automatically.
      |
     */
    'uri_param' => 'uri',

    /*
      |--------------------------------------------------------------------------
      | Proxy input: define the skip attribute
      |--------------------------------------------------------------------------
      |
      | When you call the proxy helper with this attribute set as true, you can call
      | the uri directly without pass to oauth server.
      |
     */
    'skip_param' => 'skip',

    /*
      |--------------------------------------------------------------------------
      | Proxy input: define the revoke attribute
      |--------------------------------------------------------------------------
      |
      | When you call the proxy helper with this attribute set as true, this will be
      | the last call after which the cookie will be destroyed.
      |
     */
    'revoke_param' => 'revoke',

    /*
      |--------------------------------------------------------------------------
      | Set redirect URI
      |--------------------------------------------------------------------------
      |
      | Set a redirect URI to call when the cookie expires. If you don't specify
      | any URI, the proxy helper will return a 403 proxy_cookie_expired exception.
      |
     */
    'redirect_login' => '',

    /*
      |--------------------------------------------------------------------------
      |  Cookie configuration
      |--------------------------------------------------------------------------
      |
      | This is the cookie's configuration: name of cookie and expiration minutes.
      | If time is NULL the cookie doesn't expires.
      |
     */
    'cookie_info' => [
        'name' => 'proxify',
        'time' => 1
    ],

    /*
      |--------------------------------------------------------------------------
      |  Access token send into header
      |--------------------------------------------------------------------------
      |
      | If it is true the access_token was sent to oauth server into request's header.
      |
     */
    'use_header' => false,

    /*
      |--------------------------------------------------------------------------
      |  List of client secret
      |--------------------------------------------------------------------------
      |
      | Define secrets key for each clients you need. The first is the default client
      | when you don't set client_id param in the request.
      |
     */
    'client_secrets' => [
        'client_1' => 'abc123',
        'client_2' => 'def456'
    ],
];
