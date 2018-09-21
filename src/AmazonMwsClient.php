<?php

namespace Weengs;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class AmazonMwsClient
{
    const METHOD_POST = 'POST';
    const SIGNATURE_METHOD = 'HmacSHA256';

    /**
     * @var string
     */
    private $accessKey;

    /**
     * @var string
     */
    private $secretKey;

    /**
     * @var string
     */
    private $sellerId;

    /**
     * @var array
     */
    private $marketplaceIds;

    /**
     * @var string
     */
    private $mwsAuthToken;

    /**
     * @var string
     */
    private $applicationName;

    /**
     * @var string
     */
    private $applicationVersion;

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * AmazonMwsClient constructor.
     *
     * @param string $accessKey - also known as "AWS Access Key ID"
     * @param string $secretKey
     * @param string $sellerId
     * @param array $marketplaceIds
     * @param string $mwsAuthToken
     * @param string $applicationName
     * @param string $applicationVersion
     * @param string|null $baseUrl - default is US, see the possible values. UK is https://mws.amazonservices.co.uk for example
     */
    public function __construct(
        string $accessKey,
        string $secretKey,
        string $sellerId,
        array $marketplaceIds,
        string $mwsAuthToken,
        string $applicationName = 'WeengsAmazonMwsClient',
        string $applicationVersion = '1.0',
        string $baseUrl = 'https://mws.amazonservices.com'
    )
    {
        $needle = 'https://mws.amazonservices';

        if (strpos($baseUrl, $needle) === false) {
            throw new \InvalidArgumentException(
                sprintf('Base URl must contain "%s", received "%s"', $needle, $baseUrl)
            );
        }

        if (is_null($applicationName) || $applicationName === '') {
            throw new \InvalidArgumentException('Application name cannot be null');
        }

        if (is_null($applicationVersion) || $applicationVersion === "") {
            throw new \InvalidArgumentException('Application version cannot be null');
        }

        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->sellerId = $sellerId;
        $this->marketplaceIds = $marketplaceIds;
        $this->mwsAuthToken = $mwsAuthToken;
        $this->applicationName = $applicationName;
        $this->applicationVersion = $applicationVersion;
        $this->baseUrl = $baseUrl;
    }

    /**
     * Sends the request to Amazon MWS API
     *
     * Check their documentation and scratchpad to learn all params and actions available
     * @link  http://docs.developer.amazonservices.com/en_UK/dev_guide/DG_Registering.html
     * @link  https://mws.amazonservices.co.uk/scratchpad/index.html
     *
     * @param string $action
     * @param string $versionUri
     * @param array $optionalParams
     *
     * @param bool $debug
     * @return \SimpleXMLElement
     * @throws GuzzleException
     */
    public function send(string $action, string $versionUri, array $optionalParams = [], bool $debug = false): \SimpleXMLElement
    {
        $params = array_merge($optionalParams, $this->buildRequiredParams($action, $versionUri));

        $queryString = $this->genQuery($params, $versionUri);

        $client = new Client([
            'debug' => $debug,
            'base_uri'    => $this->baseUrl,
            'body'        => $queryString,
            'http_errors' => false,
            'headers'     => [
                'User-Agent' => $this->generateUserAgent(),
                'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
            ],
        ]);

        $response = $client->request(self::METHOD_POST, $versionUri);

        $responsestr = (string) $response->getBody()->getContents();
        if ($responsestr{0} == '<') {
            return simplexml_load_string($responsestr);
        }

        return $responsestr;
    }

    /**
     * Generate the user agent header
     *
     * @return string
     */
    protected function generateUserAgent(): string
    {
        return sprintf(
            '%s/%s(Language=PHP/%s; Platform=%s/%s/%s)',
            $userAgent = $this->applicationName,
            $this->applicationVersion,
            phpversion(),
            php_uname('s'),
            php_uname('m'),
            php_uname('r')
        );
    }

    /**
     * Formats the provided string using rawurlencode
     *
     * @param string $value
     *
     * @return string
     */
    protected function urlencode($value): string
    {
        return rawurlencode($value);
    }

    /**
     * Fuses all of the parameters together into a string, copied from Amazon
     *
     * @param array $parameters
     *
     * @return string
     */
    protected function getParametersAsString(array $parameters): string
    {
        $queryParameters = [];
        foreach ($parameters as $key => $value) {
            $queryParameters[] = $key . '=' . $this->urlencode($value);
        }

        return implode('&', $queryParameters);
    }

    /**
     * Generates the string to sign, copied from Amazon
     *
     * @param array $parameters
     * @param string $uri
     *
     * @return string
     */
    protected function calculateStringToSign(array $parameters, string $uri): string
    {
        $data = self::METHOD_POST;
        $data .= "\n";
        $endpoint = parse_url(sprintf('%s%s', $this->baseUrl, $uri));

        $data .= $endpoint['host'];
        $data .= "\n";
        $uri = array_key_exists('path', $endpoint) ? $endpoint['path'] : null;

        if (!isset ($uri)) {
            $uri = "/";
        }

        $uriencoded = implode("/", array_map([$this, "urlencode"], explode("/", $uri)));
        $data .= $uriencoded;
        $data .= "\n";
        uksort($parameters, 'strcmp');

        $data .= $this->getParametersAsString($parameters);

        return $data;
    }

    /**
     * Handles generation of the signed query string.
     *
     * This method uses the secret key from the config file to generate the
     * signed query string.
     * It also handles the creation of the timestamp option prior.
     *
     * @param array $params
     * @param string $uri
     *
     * @return string query string to send in the body
     */
    protected function genQuery(array $params, string $uri): string
    {
        $params['Timestamp'] = $this->genTime();

        unset($params['Signature']);

        $params['Signature'] = $this->signParameters($params, $uri);

        return $this->getParametersAsString($params);
    }

    /**
     * Generates timestamp in ISO8601 format.
     *
     * This method creates a timestamp from the provided string in ISO8601 format.
     * The string given is passed through <i>strtotime</i> before being used. The
     * value returned is actually two minutes early, to prevent it from tripping up
     * Amazon. If no time is given, the current time is used.
     *
     * @param string $time [optional] <p>The time to use. Since this value passed through <i>strtotime</i> first,
     *                     values such as "-1 month" or "10 September 2000" are fine.
     *                     Defaults to the current time.</p>
     *
     * @return string Unix timestamp of the time, minus 2 minutes.
     */
    protected function genTime(string $time = null): string
    {
        $timestamp = time();

        if ($time) {
            $timestamp = strtotime($time);
        }

        return date('Y-m-d\TH:i:sO', $timestamp - 120);
    }

    /**
     * Validates signature and sets up signing of them, copied from Amazon
     *
     * @param array $parameters
     * @param string $uri
     *
     * @return string signed string
     */
    protected function signParameters(array $parameters, string $uri): string
    {
        $stringToSign = $this->calculateStringToSign($parameters, $uri);

        return $this->sign($stringToSign);
    }

    /**
     * Runs the hash, copied from Amazon
     * Only HmacSHA256 is available
     * Uses the Amazon Secret Key as key
     *
     * @param string $data
     *
     * @return string
     */
    protected function sign(string $data): string
    {
        return base64_encode(
            hash_hmac('sha256', $data, $this->secretKey, true)
        );
    }

    /**
     * @param string $action
     *
     * @param string $versionUri
     * @return array
     */
    protected function buildRequiredParams(string $action, string $versionUri): array
    {
        // extract version from url
        $version = (explode('/', $versionUri));

        $requiredParams = [
            'AWSAccessKeyId'     => $this->accessKey,
            'Action'             => $action,
            'SellerId'           => $this->sellerId,
            'MWSAuthToken'       => $this->mwsAuthToken,
            'SignatureVersion'   => 2,
            'Version'            => end($version),
            'SignatureMethod'    => self::SIGNATURE_METHOD
        ];

        $key = 1;
        foreach ($this->marketplaceIds as $marketplaceId) {
            $param = sprintf('MarketplaceId.Id.%s', $key);

            $requiredParams[$param] = $marketplaceId;
            $key++;
        }

        return $requiredParams;
    }
}
