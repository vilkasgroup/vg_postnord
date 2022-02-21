<?php

declare(strict_types=1);

namespace Vilkas\Postnord\Client;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

use Exception;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;

class PostnordClient
{
    /**
     * Client for making HTTP requests.
     *
     * @var HttpClient
     */
    protected $httpClient;

    /**
     * API key.
     *
     * @var string
     */
    protected $apikey;

    /**
     * Hostname for HTTP requests.
     *
     * @var string
     */
    protected $host;

    /**
     * Logger (Monolog by default).
     *
     * @var AbstractLogger
     */
    protected $logger;

    /**
     * @throws Exception
     */
    public function __construct(string $host, string $apikey)
    {
        if (empty($host) || empty($apikey)) {
            throw new Exception('Missing host or apikey');
        }

        if ('http' !== substr($host, 0, 4)) {
            $host = 'https://' . $host;
        }

        $this->host = $host;
        $this->apikey = $apikey;

        $this->httpClient = HttpClient::create();

        if (defined('_PS_VERSION_') && defined('_PS_ROOT_DIR_')) {
            $formatter = new LineFormatter(null, null, true, true);
            $handler = new StreamHandler(_PS_ROOT_DIR_ . '/var/logs/postnord-client.log');
            $handler->setFormatter($formatter);

            $this->logger = new Logger('vg_postnord_client');
            $this->logger->pushHandler($handler);
        } else {
            $this->logger = new NullLogger();
        }
    }

    /**
     * Build url with the hostname and endpoint and possible getParameters.
     */
    public function buildUrl(string $endpoint): string
    {
        $template = '{host}{path}';
        $data = [
            '{host}' => $this->host,
            '{path}' => $endpoint,
        ];

        return str_replace(array_keys($data), array_values($data), $template);
    }

    /**
     * Do a request and check that the response is somewhat valid.
     *
     * @param string method    one of GET POST PUT etc
     * @param string endpoint  path of the url to call
     * @param array  options   parameters for HttpClient
     *
     * @return array json_decoded response
     *
     * @throws ExceptionInterface
     * @throws Exception
     */
    public function doRequest(string $method, string $endpoint, array $options): array
    {
        $url = $this->buildUrl($endpoint);

        try {
            $response = $this->httpClient->request($method, $url, $options);
            $status = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            $this->logger->error(
                'Unsupported option passed to HttpClient',
                ['exception' => $e->getMessage(), 'options' => json_encode($options)]
            );
            throw $e;
        }

        try {
            // this will throw for all 300-599 and other errors
            $content = $response->getContent();

            /*
            if ($response->getStatusCode() === 200) {
                // try to read the response, it will probably give nice error information
                $content = $response->getContent(false);
                $httpLogs = $response->getInfo('debug');
                // TODO: log the error
                //var_dump($httpLogs . $content);
                throw new Exception('Error while calling service http dump: ' . $httpLogs . $content);

                return [];
            }

            $content = $response->getContent(false);
            */
        } catch (HttpExceptionInterface $e) {
            // for 400 error we can try to dig up a bit better response from the api
            // and throw it as a new exception for controllers to show
            if (Response::HTTP_BAD_REQUEST === $status) {
                $content = $response->getContent(false);

                $results = json_decode($content, true);
                if (null === $results) {
                    // TODO: probably needs more information
                    $this->logger->error('Could not decode JSON response');
                    throw new Exception('Could not decode JSON response');
                }

                if (is_array($results) && array_key_exists('servicePointInformationResponse', $results)) {
                    $servicePointInformationResponse = $results['servicePointInformationResponse'];
                    if (array_key_exists('compositeFault', $servicePointInformationResponse)) {
                        $compositeFaults = $servicePointInformationResponse['compositeFault']['faults'];
                        $msg = '';
                        foreach ($compositeFaults as $compositeFault) {
                            $msg .= $compositeFault['explanationText'];
                        }
                        throw new Exception($msg);
                    }
                }
            }

            // try to return the content as is, it probably contains some valid debug data
            $content = $e->getResponse()->getContent(false);
            $this->logger->error(
                'API request response other than 200',
                ['status' => $status, 'content' => $content, 'exception' => $e]
            );
            throw new Exception($content);
        } catch (TransportExceptionInterface $e) {
            // TODO: make this better
            $this->logger->error('Network error occurred', ['exception' => $e]);
            throw $e;
        } catch (Exception $e) {
            // TODO: make this better
            // something bad happened
            $this->logger->error('Something bad happened', ['exception' => $e]);
            throw $e;
        }

        // decode the json response
        $results = json_decode($content, true);
        if (null === $results) {
            // TODO: probably needs more information
            $this->logger->error('Could not decode JSON response');
            throw new Exception('Could not decode JSON response');
        }

        return $results;
    }

    /**
     * Get service points by address.
     *
     * https://guides.atdeveloper.postnord.com/#747cfedf-fa97-4145-8a3e-5031c38416f9
     *
     * @throws Exception
     */
    public function getServicePointsByAddress(array $parameters): array
    {
        $defaults = [
            'returnType'            => 'json',
            'context'               => 'optionalservicepoint',
            'responseFilter'        => 'public', // probably something that we always want
            'typeId'                => '', // "type of the service point" or service code, see module configuration page
            'numberOfServicePoints' => 100, // let's try to keep this high enough by default
            'srId'                  => 'EPSG:4326', // https://en.wikipedia.org/wiki/World_Geodetic_System
        ];
        $parameters = $this->mergeOptions($defaults, $parameters);
        $options['query'] = $parameters;
        $options['timeout'] = 3; // maybe enough?

        try {
            $response = $this->doRequest('GET', '/rest/businesslocation/v5/servicepoints/nearest/byaddress', $options);
        } catch (Exception $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }

        if (array_key_exists('servicePointInformationResponse', $response)) {
            return $response['servicePointInformationResponse'];
        }

        throw new Exception('servicePointInformationResponse missing from response');
    }

    /**
     * Get service point information by id
     *
     * @throws ExceptionInterface
     * @throws Exception
     */
    public function getServicePointById(array $parameters): array
    {
        $defaults = [
            'returnType'     => 'json',
            'responseFilter' => 'public'
        ];
        $parameters = $this->mergeOptions($defaults, $parameters);
        $options['query'] = $parameters;

        try {
            $response = $this->doRequest('GET', '/rest/businesslocation/v5/servicepoints/ids', $options);
        } catch (Exception $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }

        if (array_key_exists('servicePointInformationResponse', $response)) {
            return $response['servicePointInformationResponse']['servicePoints'][0];
        }

        throw new Exception('servicePointInformationResponse missing from response');
    }

    /**
     * Get basic service codes.
     *
     * https://guides.atdeveloper.postnord.com/#0c2721e2-3aa8-4bbb-bf39-049721601c01
     */
    public function getBasicServiceCodes(): array
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
     * Helper for BasicServiceCodes that fetches only the services for one issuerCountry.
     */
    public function getBasicServiceCodesFilterByIssuerCountryCode(string $countryCode): array
    {
        $rawData = $this->getBasicServiceCodes();
        $all = $rawData['data'];

        foreach ($all as $oneIssuer) {
            if ($oneIssuer['issuerCountryCode'] == $countryCode) {
                return $oneIssuer['serviceCodeDetails'];
            }
        }

        return [];
    }

    /**
     * Get additional service codes.
     *
     * https://guides.atdeveloper.postnord.com/#ee279552-541c-4220-a843-ccdda8a048f7
     */
    public function getAdditionalServiceCodes(): array
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
     * Get Valid Combinations of Service Codes.
     *
     * https://guides.atdeveloper.postnord.com/#479cf9ca-4763-42e5-91ac-ab24812343b4
     */
    public function getValidCombinationsOfServiceCodes(): array
    {
        $defaults = [];
        $parameters = $this->mergeOptions($defaults, []);
        $options['query'] = $parameters;

        try {
            $response = $this->doRequest(
                'GET',
                '/rest/shipment/v3/edi/servicecodes/adnlservicecodes/combinations',
                $options
            );
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
    public function getSurchargeHealthCheck(): array
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
     * Helper for merging defaults and options. Options will overwrite defaults.
     *
     * Always injects apikey into parameters, it seems to be required for almost everything.
     */
    private function mergeOptions(array $defaults, array $options): array
    {
        // always inject apikey if it was not in the defaults
        if (!array_key_exists('apikey', $defaults)) {
            $defaults['apikey'] = $this->apikey;
        }

        return array_replace($defaults, $options);
    }

    /**
     * Create booking with given info
     * return PDF label if $labelInfo is provided
     * can return ZPL as well, it's not that different
     * Check the testCreateBooking for the correct data format.
     *
     * @param string $customerEmail email of customer
     * @param object $customerAddress Prestashop address object (customer address)
     * @param array $order information about an order
     * @param array $shopAddress Merchant address, in module settings
     * @param string $country customer's country
     * @param array $labelInfo label printout format (Paper size, number, etc.) from PostNord
     * @param array $pickupAddress information about a pickup point, comes from PostNord
     *
     * @return array PostNord booking confirmation with/without PDF label
     *
     * @throws ExceptionInterface with error message from PostNord
     */
    public function createBooking(
        string $customerEmail,
        object $customerAddress,
        array $order,
        array $shopAddress,
        array $returnAddress,
        string $country = 'FI',
        array $labelInfo = [],
        array $pickupAddress = []
    ): array {
        $defaults = [];
        $parameters = $this->mergeOptions($defaults, $labelInfo);
        $options['query'] = $parameters;
        $options['json'] = $this->generateBooking(
            $customerEmail,
            $customerAddress,
            $order,
            $shopAddress,
            $returnAddress,
            $country,
            $pickupAddress
        );

        try {
            $this->logger->debug('Create booking with:' . PHP_EOL . json_encode($options, JSON_PRETTY_PRINT));
            if (empty($labelInfo)) {
                $response = $this->doRequest('POST', '/rest/shipment/v3/edi', $options);
            } else {
                $response = $this->doRequest('POST', '/rest/shipment/v3/edi/labels/pdf', $options);
            }
            if (isset(
                $response['labelPrintout'][0]['printout']['data']
            )) {
                // remove base64 pdf
                $responseWithoutBase64 = $response;
                unset($responseWithoutBase64['labelPrintout'][0]['printout']['data']);
                $this->logger->debug('Booking created with data:' . PHP_EOL . json_encode($responseWithoutBase64, JSON_PRETTY_PRINT));
            } else {
                $this->logger->debug('Booking created with data:' . PHP_EOL . json_encode($response, JSON_PRETTY_PRINT));
            }
        } catch (Exception $e) {
            // TODO: Log error
            $this->logger->error('Error create booking', ["exception" => $e]);
            $error = json_decode($e->getMessage(), true);
            throw new Exception($error['message']);
        }

        return $response;
    }

    /**
     * @param string $labelId id of the label or item, they use the same id (not the id of the booking)
     * @param array $labelInfo label printout format (Paper size, number, etc.) from PostNord
     *
     * @return array PDF label from PostNord
     */
    public function getPDFLabelFromId(string $labelId, array $labelInfo): array
    {
        $defaults = [];
        $parameters = $this->mergeOptions($defaults, $labelInfo);
        $options['query'] = $parameters;
        $options['json'] = [['id' => $labelId]];
        try {
            $this->logger->debug('Get label with:' . PHP_EOL . json_encode($options, JSON_PRETTY_PRINT));
            $response = $this->doRequest('POST', '/rest/shipment/v3/labels/ids/pdf', $options);
            if (isset(
                $response['labelPrintout'][0]['printout']['data']
            )) {
                // remove base64 pdf before logging
                $responseWithoutBase64 = $response;
                unset($responseWithoutBase64['labelPrintout'][0]['printout']['data']);
                $this->logger->debug('Booking created with data:' . PHP_EOL . json_encode($responseWithoutBase64, JSON_PRETTY_PRINT));
            }
        } catch (Exception $e) {
            // TODO: Log error
            $this->logger->error('Error getting label' . $e->getMessage());
            $error = json_decode($e->getMessage(), true);
            throw new Exception($error['message']);
        }

        return $response;
    }

    /**
     * @param string $itemId id of the label or item, they use the same id (not the id of the booking)
     * @param array $labelInfo label printout format (Paper size, number, etc.) from PostNord
     *
     * @return array PDF label from PostNord
     */
    public function getReturnPDFLabelFromId(string $itemId, array $labelInfo): array
    {
        $defaults = [];
        $parameters = $this->mergeOptions($defaults, $labelInfo);
        $options['query'] = $parameters;
        $options['json'] = [['return' => ['id' => $itemId]]];
        try {
            $this->logger->debug('Get label with:' . PHP_EOL . json_encode($options, JSON_PRETTY_PRINT));
            $response = $this->doRequest('POST', '/rest/shipment/v3/returns/ids/labels/pdf', $options);
            if (isset(
                $response['labelPrintout'][0]['printout']['data']
            )) {
                // remove base64 pdf before logging
                $responseWithoutBase64 = $response;
                unset($responseWithoutBase64['labelPrintout'][0]['printout']['data']);
                $this->logger->debug('Booking created with data:' . PHP_EOL . json_encode($responseWithoutBase64, JSON_PRETTY_PRINT));
            }
        } catch (Exception $e) {
            // TODO: Log error
            $this->logger->error('Error getting label' . $e->getMessage());
            var_dump($e->getMessage());
            $error = json_decode($e->getMessage(), true);
            throw new Exception($error['message']);
        }

        return $response;
    }

    // Just to get issuer code
    // Z11=PostNord Denmark, Z12=PostNord Sweden, Z13=PostNord Norway, Z14=PostNord Finland
    private function getIssuerCode(string $country): string
    {
        switch ($country) {
            case 'NO':
                return 'Z13';
            case 'SE':
                return 'Z12';
            case 'DK':
                return 'Z11';
            case 'FI':
            default:
                return 'Z14';
        }
    }

    /**
     * generate request body for booking
     * params is the same as createBooking.
     *
     * @param string $customerEmail
     * @param object $customerAddress Prestashop address object (customer address)
     * @param array $order information about an order
     * @param array $shopAddress Merchant address, in the setting
     * @param string $country customer's country
     * @param array $pickupAddress information about a pickup point, comes from PostNord
     *
     * @return array request body to create booking
     */
    protected function generateBooking(
        string $customerEmail,
        object $customerAddress,
        array $order,
        array $shopAddress,
        array $returnAddress,
        string $country = 'FI',
        array $pickupAddress = []
    ): array {
        $datetime = date(DATE_ISO8601);
        $returnAddress = [
            'return_name' => !empty($returnAddress['return_name']) ? $returnAddress['return_name'] : $shopAddress['shop_name'],
            'return_street' => !empty($returnAddress['return_street']) ? $returnAddress['return_street'] : $shopAddress['shop_street'],
            'return_postcode' => !empty($returnAddress['return_postcode']) ? $returnAddress['return_postcode'] : $shopAddress['shop_postcode'],
            'return_city' => !empty($returnAddress['return_city']) ? $returnAddress['return_city'] : $shopAddress['shop_city'],
            'return_country' => !empty($returnAddress['return_country']) ? $returnAddress['return_country'] : $shopAddress['shop_country'],
        ];
        $body = [
            'messageDate' => $datetime,
            'messageFunction' => 'Instruction',
            'messageId' => uniqid(),
            'application' => [
                'name' => 'vg_postnord',
                'version' => '0.0.1',
            ],
            'updateIndicator' => 'Original', //enum: Original, Update, Deletion
            'shipment' => [
                [
                    'shipmentIdentification' => [
                        'shipmentId' => $order['id'], //from data?
                    ],
                    'dateAndTimes' => [
                        'loadingDate' => $datetime,
                    ],
                    'service' => [
                        'basicServiceCode' => $order['basicServiceCode'], //from data
                        'additionalServiceCode' => $order['additionalServiceCode'], //from data
                    ],
                    'freeText' => [], //from data
                    'numberOfPackages' => [
                        'value' => $order['numberOfPackages'],
                    ],
                    'totalGrossWeight' => [
                        'value' => $order['grossWeight'], //from data
                        'unit' => 'KGM',
                    ],
                    'parties' => [
                        'consignor' => [
                            'issuerCode' => $this->getIssuerCode($shopAddress['shop_country']),
                            'partyIdentification' => [
                                'partyId' => $shopAddress['shop_party_id'],
                                'partyIdType' => '160',
                            ],
                            'party' => [
                                'nameIdentification' => [
                                    'name' => $shopAddress['shop_name'],
                                ],
                                'address' => [
                                    'streets' => [$shopAddress['shop_street']],
                                    'postalCode' => $shopAddress['shop_postcode'],
                                    'city' => $shopAddress['shop_city'],
                                    'countryCode' => $shopAddress['shop_country'],
                                ],
                            ],
                        ],
                        'returnParty' => [
                            'party' => [
                                'nameIdentification' => [
                                    'name' => $returnAddress['return_name'],
                                ],
                                'address' => [
                                    'streets' => [$returnAddress['return_street']],
                                    'postalCode' => $returnAddress['return_postcode'],
                                    'city' => $returnAddress['return_city'],
                                    'countryCode' => $returnAddress['return_country'],
                                ],
                            ],
                        ],
                        'consignee' => [
                            'issuerCode' => $this->getIssuerCode($country),
                            'party' => [
                                'nameIdentification' => [
                                    'name' => "{$customerAddress->firstname} {$customerAddress->lastname}",
                                ],
                                'address' => [
                                    'streets' => ["{$customerAddress->address1} {$customerAddress->address2}"],
                                    'postalCode' => $customerAddress->postcode,
                                    'city' => $customerAddress->city,
                                    'countryCode' => $country,
                                ],
                                'contact' => [
                                    'contactName' => "{$customerAddress->firstname} {$customerAddress->lastname}",
                                    'emailAddress' => $customerEmail,
                                    'smsNo' => $customerAddress->phone,
                                ],
                            ],
                        ],
                    ],
                    'goodsItem' => [
                        [
                            'packageTypeCode' => 'PC',
                            'items' => [
                                [
                                    'itemIdentification' => [
                                        'itemId' => $order['id'],
                                        'itemIdType' => 'SSCC', //SSCC for Nordic and DPD to other countries
                                    ],
                                    'grossWeight' => [
                                        'value' => $order['grossWeight'],
                                        'unit' => 'KGM',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if (!empty($pickupAddress)) {
            $body['shipment'][0]['parties']['deliveryParty'] = [
                'partyIdentification' => [
                    'partyId' => $pickupAddress['servicePointId'],
                    'partyIdType' => '156',
                ],
                'party' => [
                    'nameIdentification' => [
                        'name' => $pickupAddress['name'],
                    ],
                    'address' => [
                        'streets' => [
                            "{$pickupAddress['visitingAddress']['streetName']} {$pickupAddress['visitingAddress']['streetNumber']}",
                        ],
                        'postalCode' => $pickupAddress['visitingAddress']['postalCode'],
                        'city' => $pickupAddress['visitingAddress']['city'],
                        'countryCode' => $pickupAddress['visitingAddress']['countryCode'],
                    ],
                ],
            ];
        }

        return $body;
    }
}
