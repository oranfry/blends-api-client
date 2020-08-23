<?php
class ApiRequest
{
    public $endpoint;
    public $data;
    public $contentType = 'application/json';
    public $headers = [];
    public $method = 'GET';

    public function __construct($endpoint = null)
    {
        if ($endpoint) {
            $this->endpoint = $endpoint;
        }
    }
}