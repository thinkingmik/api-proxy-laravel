<?php

/**
 * @package   andreoli/api-proxy-laravel
 * @author    Michele Andreoli <michi.andreoli[at]gmail.com>
 * @copyright Copyright (c) Michele Andreoli
 * @license   http://mit-license.org/
 * @link      https://github.com/mandreoli/api-proxy-laravel
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
      | Proxy input: define if proxy need to save access token into a cookie
      |--------------------------------------------------------------------------
      |
      | When proxy find this attribute in the request and its value is Y or TRUE
      | the proxy knows that this is an access token request and creates a cookie.
      | GET http://laravel.dev/access_token?uri=http://api/token&client_id=myclient&username=xxx&password=xxx&token=true
      |
     */
    'request_mode' => 'mode',

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
      |  List of client secret
      |--------------------------------------------------------------------------
      |
      | Define secrets key for each clients you need.
      |
     */
    'client_secrets' => [
        'client_1' => 'abc123',
        'client_2' => 'abc456'
    ],
];
