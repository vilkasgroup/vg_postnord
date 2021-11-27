<?php

declare(strict_types=1);

namespace Vilkas\Postnord\Client;

use Exception;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

class PostnordClient
{
    /**
     * Client for making HTTP-requests
     *
     * @var HttpClient
     */
    protected $httpClient;

    /**
     * apikey
     *
     * @var string
     */
    protected $apikey;

    /**
     * hostname for http requests
     *
     * @var string
     */
    protected $host;

    public function __construct(string $host, string $apikey)
    {
        if (empty($host) || empty($apikey)) {
            throw new Exception('Missing host or apikey');
        }

        if (substr($host, 0, 4) !== 'http') {
            $host = 'https://' . $host;
        }

        $this->host = $host;
        $this->apikey = $apikey;

        $this->httpClient = HttpClient::create();
    }

    /**
     * Build url with the hostname and endpoint and possible getParameters
     */
    public function buildUrl(string $endpoint): string
    {
        $template = '{host}{path}';
        $data = [
            '{host}' => $this->host,
            '{path}' => $endpoint,
        ];

        $url = str_replace(array_keys($data), array_values($data), $template);

        return $url;
    }

    /**
     * Do a request and check that the response is somewhat valid
     *
     * method, one of GET POST PUT etc
     * endpoint path of the url to call
     * options parameters for HttpClient
     *
     * Returns json_decoded response.
     *
     * If request or decoding fails throws Exception
     */
    public function doRequest(string $method, string $endpoint, array $options): array
    {
        $url = $this->buildUrl($endpoint);

        // make the request and do a sanity check for it
        try {
            $response = $this->httpClient->request($method, $url, $options);

            if ($response->getStatusCode() != 200) {
                // try to read the response, it will probably give nice error information
                $content = $response->getContent(false);
                $httpLogs = $response->getInfo('debug');
                // TODO: log the error
                var_dump($httpLogs . $content);
                throw new Exception('Error while calling service http dump: ' . $httpLogs . $content);

                return [];
            }
            $content = $response->getContent();
        } catch (ExceptionInterface $e) {
            // TODO: log me
            throw $e;
        }

        // decode the response
        try {
            $results = json_decode($content, true);
        } catch (Exception $e) {
            // TODO: log me, json_decode failed
            throw $e;
        }

        return $results;
    }

    /**
     * Get service points by address
     *
     * https://guides.atdeveloper.postnord.com/#747cfedf-fa97-4145-8a3e-5031c38416f9
     */
    public function getServicePointsByAddress(array $parameters): array
    {
        $defaults = [
            'returnType' => 'json',
            'context' => 'optionalservicepoint',
            'responseFilter' => 'public', // probably something that we always want
            //'typeId' => 25, // TODO: what is this magic number? cannot find any information in dev documentation
            'numberOfServicePoints' => 100, // lets try to keep this high enough by default
            'srId' => 'EPSG:4326', // https://en.wikipedia.org/wiki/World_Geodetic_System
        ];
        $parameters = $this->mergeOptions($defaults, $parameters);
        $options['query'] = $parameters;

        try {
            $response = $this->doRequest('GET', '/rest/businesslocation/v5/servicepoints/nearest/byaddress', $options);
        } catch (Exception $e) {
            // TODO log the error and do something sane
            throw $e;

            return [];
        }

        if (array_key_exists('servicePointInformationResponse', $response)) {
            return $response['servicePointInformationResponse'];
        }

        throw new Exception('servicePointInformationResponse missing from response');
    }

    /**
     * Get basic service codes
     *
     * https://guides.atdeveloper.postnord.com/#0c2721e2-3aa8-4bbb-bf39-049721601c01
     */
    public function getBasicServiceCodes()
    {
        $defaults = [];
        $parameters = $this->mergeOptions($defaults, []);
        $options['query'] = $parameters;

        try {
            $response = $this->doRequest('GET', '/rest/shipment/v3/edi/servicecodes/adnlservicecodes/combinations', $options);
        } catch (Exception $e) {
            // TODO log the error and do something sane
            throw $e;

            return [];
        }

        return $response;
    }

    /**
     * Get additional service codes
     *
     * https://guides.atdeveloper.postnord.com/#ee279552-541c-4220-a843-ccdda8a048f7
     */
    public function getAdditionalServiceCodes()
    {
        $defaults = [];
        $parameters = $this->mergeOptions($defaults, []);
        $options['query'] = $parameters;

        try {
            $response = $this->doRequest('GET', '/rest/shipment/v3/edi/adnlservicecodes', $options);
        } catch (Exception $e) {
            // TODO log the error and do something sane
            throw $e;

            return [];
        }

        return $response;
    }

    /**
     * Get Valid Combinations of Service Codes
     *
     * https://guides.atdeveloper.postnord.com/#479cf9ca-4763-42e5-91ac-ab24812343b4
     */
    public function getValidCombinationsOfServiceCodes()
    {
        $defaults = [];
        $parameters = $this->mergeOptions($defaults, []);
        $options['query'] = $parameters;

        try {
            $response = $this->doRequest('GET', '/rest/shipment/v3/edi/servicecodes', $options);
        } catch (Exception $e) {
            // TODO log the error and do something sane
            throw $e;

            return [];
        }

        return $response;
    }

    /**
     * Just for testing.. seems to error out on their side atm..
     *
     * https://guides.atdeveloper.postnord.com/#cb2ac083-992b-4a3b-aaec-01ab50ea5654
     */
    public function getSurchargeHealthCheck()
    {
        $defaults = [];
        $parameters = $this->mergeOptions($defaults, []);
        $options['query'] = $parameters;

        try {
            $response = $this->doRequest('GET', '/rest/location/v1/surcharge/manage/health', $options);
        } catch (Exception $e) {
            // TODO log the error and do something sane
            throw $e;

            return [];
        }

        return $response;
    }

    /**
     * helper for merging defaults and optiosn. options will overwrite defaults.
     *
     * always injects apikey into parameters, it seems to be required for almost everything
     */
    private function mergeOptions(array $defaults, array $options): array
    {
        // always inject apikey if it was not in the defaults
        if (!array_key_exists('apikey', $defaults)) {
            $defaults['apikey'] = $this->apikey;
        }

        return array_replace($defaults, $options);
    }
}
