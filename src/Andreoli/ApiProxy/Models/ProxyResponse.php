<?php

/**
 * @package   andreoli/api-proxy-laravel
 * @author    Michele Andreoli <michi.andreoli[at]gmail.com>
 * @copyright Copyright (c) Michele Andreoli
 * @license   http://mit-license.org/
 * @link      https://github.com/mandreoli/api-proxy-laravel
 */

namespace Andreoli\ApiProxy\Models;

class ProxyResponse {

    private $clientId = null;
    private $statusCode = null;
    private $reasonPhrase = null;
    private $protocolVersion = null;
    private $content = null;

    public function __construct($clientId, $statusCode, $reasonPhrase, $protoVersion, $content) {
        $this->clientId = $clientId;
        $this->statusCode = $statusCode;
        $this->reasonPhrase = $reasonPhrase;
        $this->protocolVersion = $protoVersion;
        $this->content = $content;
    }

    public function setClientId($client) {
        $this->clientId = $client;
    }

    public function setStatusCode($status) {
        $this->statusCode = $status;
    }

    public function setReasonPhrase($phrase) {
        $this->reasonPhrase = $phrase;
    }

    public function setProtoVersion($proto) {
        $this->protocolVersion = $proto;
    }

    public function setContent($content) {
        $this->content = $content;
    }

    public function getClientId() {
        return $this->clientId;
    }

    public function getStatusCode() {
        return $this->statusCode;
    }

    public function getReasonPhrase() {
        return $this->reasonPhrase;
    }

    public function getProtoVersion() {
        return $this->protocolVersion;
    }

    public function getContent() {
        return $this->content;
    }
}
