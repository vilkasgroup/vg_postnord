<?php declare(strict_types = 1);
namespace Vilkas\Postnord\Client;

use Exception;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

class PostnordClient
{
    /**
     * Client for making HTTP-requests
     * @var HttpClient
     */
    protected $httpClient;

    /**
     * apikey
     * @var string
     */
    protected $apikey;

    /**
     * hostname for http requests
     * @var string
     */
    protected $host;


    public function __construct(string $host, string $apikey)
    {
        if (empty($host) || empty($apikey)) {
            throw new Exception("Missing host or apikey");
        }

        if (substr($host, 0, 4) !== "http") {
            $host = 'https://'.$host;
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
        $template = "{host}{path}";
        $data = [
            '{host}' => $this->host,
            '{path}' => $endpoint,
        ];

        $url = str_replace(array_keys($data), array_values($data), $template);

        return $url;
    }

    /**
     * Get service points by address
     *
     * https://guides.atdeveloper.postnord.com/#747cfedf-fa97-4145-8a3e-5031c38416f9
     *
     *
     */
    public function getServicePointsByAddress(array $options): array
    {
        $defaults = [
            'returnType' => 'json',
            'context' => 'optionalservicepoint',
            'responseFilter' => 'public', // probably something that we always want
            //'typeId' => 25, // TODO: what is this magic number? cannot find any information in dev documentation
            'numberOfServicePoints' => 100, // lets try to keep this high enough by default
            'srId' => 'EPSG:4326', // https://en.wikipedia.org/wiki/World_Geodetic_System
        ];
        $options = $this->mergeOptions($defaults, $options);

        $url = $this->buildUrl('/rest/businesslocation/v5/servicepoints/nearest/byaddress', $options);

        try {
            $response = $this->httpClient->request("GET", $url, [
                'query' => $options,
            ]);
            if ($response->getStatusCode() != 200) {
                // try to read the response, it will probably give nice error information
                $content = $response->getContent(false);
                $httpLogs = $response->getInfo('debug');
                // TODO: log the error
                //var_dump($httpLogs . $content);
                return [];
            }
            $content = $response->getContent();
        } catch (ExceptionInterface $e) {
            // TODO: log me
            throw $e;
        }

        try {
            $results = json_decode($content, true);
        } catch (Exception $e) {
            // TODO: log me
            throw $e;
        }

        // response is always wrapped in "servicePointInformationResponse" leave it out.
        // and if it does not exists something must have gone wrong
        // TODO: do we want to return the servicePoints or the servicePointInformationResponse???
        if (array_key_exists('servicePointInformationResponse', $results)) {
            return $results['servicePointInformationResponse'];
        } else {
            throw new Exception('servicePointInformationResponse missing from response');
        }

        return [];
    }

    /**
     * helper for merging defaults and optiosn. options will overwrite defaults.
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
