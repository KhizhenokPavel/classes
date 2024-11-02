<?php

namespace Pavelkhizhenok\SupportClasses\Classes;

use Pavelkhizhenok\SupportClasses\Interfaces\ApiClientInterface;

abstract class ApiClient implements ApiClientInterface {

    public const REQUEST_METHOD_GET = 'GET';
    public const REQUEST_METHOD_POST = 'POST';
    public const REQUEST_METHOD_PUT = 'PUT';
    public const REQUEST_METHOD_DELETE = 'DELETE';

    public const REQUEST_PROTOKOL_NAME_HTTP = 'http';
    public const REQUEST_PROTOKOL_NAME_HTTPS = 'https';

    public const BODY_FORMAT_NAME_JSON = 'json';
    public const BODY_FORMAT_NAME_STRING = 'string';

    public const BAD_RESPONSE_STATUS_CODE_CLASSES = ['3, 4, 5'];

    public const AVAILABLE_REQUEST_PROTOCOLS = array(
        self::REQUEST_PROTOKOL_NAME_HTTP,
        self::REQUEST_PROTOKOL_NAME_HTTPS,
    );

    public const AVAILABLE_BODY_FORMATS = array(
        self::BODY_FORMAT_NAME_JSON,
        self::BODY_FORMAT_NAME_STRING,
    );

    protected string $requestProtocol = self::REQUEST_PROTOKOL_NAME_HTTPS;
    protected string $requestUrl = '';
    protected array $requestBaseParams = array();
    protected int $requestMaxTimeout = 60;
    protected array $requestBaseHeaders = array();
    protected string $requestBodyFormat = self::BODY_FORMAT_NAME_JSON;
    protected string $responseBodyFormat = self::BODY_FORMAT_NAME_JSON;
    protected array $requestOptions = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HEADER => false,
    );

    protected string $error = '';


    public function __construct() {

        $this->initConfig($this->getConfig());

    }

    public function initConfig(array $config) {

        if (!$this->isValidConfig($config)) {
            return;
        }

        $this->setConfigValues($config);

    }

    protected function setConfigValues(array $config): void {

        if (isset($config['url'])) {
            $this->requestUrl = $config['url'];
        }

        if (isset($config['protocol'])) {
            $this->requestProtocol = $config['protocol'];
        }

        if (isset($config['params'])) {
            $this->requestBaseParams = $config['params'];
        }

        if (isset($config['maxTimeout'])) {
            $this->requestMaxTimeout = $config['maxTimeout'];
        }

        if (isset($config['baseHeaders'])) {
            $this->requestBaseHeaders = $config['baseHeaders'];
        }

        if (isset($config['requestDataFormat'])) {
            $this->requestBodyFormat = $config['requestDataFormat'];
        }

        if (isset($config['responseDataFormat'])) {
            $this->responseBodyFormat = $config['responseDataFormat'];
        }

        if (isset($config['options'])) {
            $this->requestOptions = array_merge($this->requestOptions, $config['options']);
        }

    }

    protected function isValidConfig(array $config): bool {

        if (empty($config['url'])) {
            $this->error = 'Ссылка на апи не заполнена.';
            return false;
        }

        if (!is_string($config['url'])) {
            $this->error = 'Ссылка на апи заполнена некорректно.';
            return false;
        }

        if (isset($config['params']) && !is_array($config['params'])) {
            $this->error = 'Параметры запроса по умолчанию переданы некорректно.';
            return false;
        }

        if (isset($config['protocol']) && !in_array($config['url'], static::AVAILABLE_REQUEST_PROTOCOLS)) {
            $this->error = 'Выбранный протокол пока что не поддерживается.';
            return false;
        }

        if (isset($config['maxTimeout']) && !is_integer($config['maxTimeout'])) {
            $this->error = 'Максимальное время ожидания серввера заполнено некорректно.';
            return false;
        }

        if (isset($config['baseHeaders']) && !is_array($config['baseHeaders'])) {
            $this->error = 'Базовые заголовки заполнены некорректно.';
            return false;
        }

        if (isset($config['requestDataFormat']) && !in_array($config['requestDataFormat'], static::AVAILABLE_BODY_FORMATS)) {
            $this->error = 'Данный тип запроса пока что не поддерживается.';
            return false;
        }

        if (isset($config['responseDataFormat']) && !in_array($config['responseDataFormat'], static::AVAILABLE_BODY_FORMATS)) {
            $this->error = 'Данный тип ответа пока что не поддерживается.';
            return false;
        }

        if (isset($config['options']) && !is_array($config['options'])) {
            $this->error = 'Параметры запроса заполнены некорректно.';
            return false;
        }

        return true;

    }

    protected function sendRequest(string $requestUrl = '', array $params = array(), string $method = self::REQUEST_METHOD_GET, array $headers = array()): array|bool {

        if (!empty($error)) {
            return false;
        }

        $params = array_merge($this->requestBaseParams, $params);

        $ch = curl_init();

        curl_setopt_array($ch, $this->getRequestOptions($requestUrl, $params, $method, $headers));

        $response = curl_exec($ch);

        if ($response === false) {
            $this->error = curl_error($ch);
            return false;
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);

        return array(
            'code' => (string) $httpCode,
            'response' => $this->formatResonse($response),
        );

    }

    protected function getResponseBody(array $response): array|bool {

        if (
            empty($response['code']) 
            || !isset($response['response'])
            || in_array($response['code'][0], static::BAD_RESPONSE_STATUS_CODE_CLASSES)
        ) {
            return false;
        }

        return $response['response'];

    }

    protected function sendRequestAndGetResponseBody(string $requestUrl = '', array $params = array(), string $method = self::REQUEST_METHOD_GET, array $headers = array()): array|bool {

        return $this->getResponseBody(
            $this->sendRequest($requestUrl, $params, $method, $headers)
        );

    }

    protected function getRequestOptions(string $requestUrl, array $params, string $method, array $headers): array {

        $requestUri = $this->requestProtocol . '://' . $this->requestUrl . $requestUrl;

        if ($method == self::REQUEST_METHOD_GET) {
            if (!empty($params)) {
                $requestUri .= '?' . http_build_query($params);
            }
        } else {
            if ($method = self::REQUEST_METHOD_POST) {
                $this->requestOptions[CURLOPT_POST] = true;
            } else {
                $this->requestOptions[CURLOPT_CUSTOMREQUEST] = $method;
            }

            $this->requestOptions[CURLOPT_POSTFIELDS] = $this->getCurloptPostField($params);
        }

        if (!empty($headers)) {
            $this->requestOptions[CURLOPT_HTTPHEADER] = $headers;
        }

        $this->requestOptions[CURLOPT_URL] = $requestUri;
        $this->requestOptions[CURLOPT_TIMEOUT] = $this->requestMaxTimeout;

        return $this->requestOptions;

    }

    protected function getCurloptPostField(array $params): bool|string {

        switch($this->requestBodyFormat) {
            case self::BODY_FORMAT_NAME_JSON:
                return $this->getJsonPostFields($params);
            case self::BODY_FORMAT_NAME_STRING:
                return $this->getStringPostFields($params);
        }

        return false;

    }

    protected function getStringPostFields(array $params): string {

        return http_build_query($params, '', '&');
        
    }

    protected function getJsonPostFields(array $params): bool|string {

        return json_encode($params, JSON_UNESCAPED_UNICODE);

    }

    protected function formatResonse($response): array|bool|string {

        switch($this->responseBodyFormat) {
            case self::BODY_FORMAT_NAME_JSON:
                return $this->getResponseByJson($response);
            case self::BODY_FORMAT_NAME_STRING:
                return $this->getResponseByString($response);
        }

        return false;

    }

    protected function getResponseByJson(string $response): array|bool {

        $response = json_decode($response, true);

        if ($response === false) {
            return false;
        }

        return $response;

    }

    protected function getResponseByString(string $response): string {

        return $response;

    }

    public function getError(): string {

        return $this->error;

    }

}