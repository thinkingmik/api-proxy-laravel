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
      | Define the URI attribute name (and others input parameters)
      |--------------------------------------------------------------------------
      |
      | When you call the proxy helper, you need to pass the real URI of the API endpoint
      | in this parameter. If the URI is encoded the proxy will decode it automatically.
      | The grant_type and client_id parameters are optional.
      |
     */
    'uri_param' => 'uri',
    'grant_type_param' => 'grant_type',
    'client_id_param' => 'client_id',

    /*
      |--------------------------------------------------------------------------
      |  Attributes for oauth call
      |--------------------------------------------------------------------------
      |
      | The client secret attribute name for oauth call. This parameter will be add
      | by the proxy helper when grant_type_param value is 'password'.
      |
     */
    'client_secret_param' => 'client_secret',
    'access_token_param' => 'access_token',


    'cookie_info' => [
        'name' => 'proxy',
        'time' => 5
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
