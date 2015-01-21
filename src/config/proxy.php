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
      | Define the URI attribute name
      |--------------------------------------------------------------------------
      |
      | When you call the proxy helper, you need to pass the real URI of the API endpoint
      | in this parameter. If the URI is encoded the proxy will decode it automatically.
      |
     */
    'uri_param' => 'uri',

    /*
      |--------------------------------------------------------------------------
      |  Attributes for login request call
      |--------------------------------------------------------------------------
      |
      | If request call contains these three attributes, proxy helper will add the client
      | secrets for the authentication with the oauth password grant flow.
      |
     */
    'login_client_id_param'     => 'client_id',
    'login_username_param'      => 'username',
    'login_password_param'      => 'password',

    'login_client_secret_param' => 'client_secret',

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
