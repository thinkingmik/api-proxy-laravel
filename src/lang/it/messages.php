<?php

/**
 * @package   andreoli/api-proxy-laravel
 * @author    Michele Andreoli <michi.andreoli[at]gmail.com>
 * @copyright Copyright (c) Michele Andreoli
 * @license   http://mit-license.org/
 * @link      https://github.com/mandreoli/api-proxy-laravel
 */

return array(
	'access_token_ok' => 'Access token recuperato correttamente',
	'proxy_missing_param' => 'Manca il parametro obbligatorio <b>:param</b> nella richiesta',
	'missing_client_secret' => 'Manca la chiave segreta per il client <b>:client</b>',
	'proxy_cookie_expired' => 'Cookie scaduto o non trovato. Ritorna al form di login.',
	'proxy_cookie_invalid' => 'Formato del cookie non valido. Manca l\'attributo <b>:param</b>.'
);