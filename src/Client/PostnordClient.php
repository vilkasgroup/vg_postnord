<?php

declare(strict_types=1);

namespace Vilkas\Postnord\Client;

use Exception;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class PostnordClient
{
	/**
	 * Client for making HTTP requests
	 *
	 * @var HttpClient
	 */
	protected $httpClient;

	/**
	 * API key
	 *
	 * @var string
	 */
	protected $apikey;

	/**
	 * Hostname for HTTP requests
	 *
	 * @var string
	 */
	protected $host;

	/**
	 * Logger (Monolog by default)
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

		if (substr($host, 0, 4) !== 'http') {
			$host = 'https://' . $host;
		}

		$this->host = $host;
		$this->apikey = $apikey;

		$this->httpClient = HttpClient::create();

		if (defined("_PS_VERSION_") && defined("_PS_ROOT_DIR_")) {
			$this->logger = new Logger("vg_postnord");
			$this->logger->pushHandler(new StreamHandler(_PS_ROOT_DIR_ . "/var/logs/postnord-client.log"));
		} else {
			$this->logger = new NullLogger();
		}
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

		return str_replace(array_keys($data), array_values($data), $template);
	}

	/**
	 * Do a request and check that the response is somewhat valid
	 *
	 * @param string method    one of GET POST PUT etc.
	 * @param string endpoint  path of the url to call
	 * @param array  options   parameters for HttpClient
	 *
	 * @return array json_decoded response.
	 *
	 * @throws ExceptionInterface
	 * @throws Exception
	 */
	public function doRequest(string $method, string $endpoint, array $options): array
	{
		$url = $this->buildUrl($endpoint);

		try {
			$response = $this->httpClient->request($method, $url, $options);
			$status   = $response->getStatusCode();
		} catch (TransportExceptionInterface $e) {
			$this->logger->error("Unsupported option passed to HttpClient", ["exception" => $e->getMessage(), "options" => json_encode($options)]);
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
			if ($status === Response::HTTP_BAD_REQUEST) {
				$content = $response->getContent(false);

				$results = json_decode($content, true);
				if ($results === null) {
					// TODO: probably needs more information
					$this->logger->error("Could not decode JSON response");
					throw new Exception("Could not decode JSON response");
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
			$this->logger->error("API request response other than 200", ["status" => $status, "content" => $content, "exception" => $e->getMessage()]);
			throw new Exception($content);
		} catch (TransportExceptionInterface $e) {
			// TODO: make this better
			$this->logger->error("Network error occurred", ["exception" => $e->getMessage()]);
			throw $e;
		} catch (Exception $e) {
			// TODO: make this better
			// something bad happened
			$this->logger->error("Something bad happened", ["exception" => $e->getMessage()]);
			throw $e;
		}

		// decode the json response
		$results = json_decode($content, true);
		if ($results === null) {
			// TODO: probably needs more information
			$this->logger->error("Could not decode JSON response");
			throw new Exception("Could not decode JSON response");
		}

		return $results;
	}

	/**
	 * Get service points by address
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
	 * Get basic service codes
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
	 * Helper for BasicServiceCodes that fetches only the services for one issuerCountry
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
	 * Get additional service codes
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
	 * Get Valid Combinations of Service Codes
	 *
	 * https://guides.atdeveloper.postnord.com/#479cf9ca-4763-42e5-91ac-ab24812343b4
	 */
	public function getValidCombinationsOfServiceCodes(): array
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
	 * currently asked for $shopAddress (testing purpose) but can be changed to extract from configuration
	 */

	public function createBooking(string $customerEmail, $customerAddress, array $order, array $pickupAddress, array $shopAddress, string $country = 'FI')
	{
		$defaults = [];
		$parameters = $this->mergeOptions($defaults, []);
		$options['query'] = $parameters;
		$options['json'] = $this->generateBooking($customerEmail, $customerAddress, $order, $pickupAddress, $shopAddress, $country);

		try {
			$response = $this->doRequest('POST', '/rest/shipment/v3/edi', $options);
		} catch (Exception $e) {
			// TODO: Log error
			throw $e;

			return [];
		}

		return $response;
	}

	/**
	 * Similar to createBooking but with PDF label or ZFL in the future 
	 */
	public function createBookingWithPDF(string $customerEmail, $customerAddress, array $order, array $pickupAddress, array $shopAddress, string $country = 'FI', array $labelInfo)
	{
		$defaults = [];
		$parameters = $this->mergeOptions($defaults, $labelInfo);
		$options['query'] = $parameters;
		$options['json'] = $this->generateBooking($customerEmail, $customerAddress, $order, $pickupAddress, $shopAddress, $country);

		try {
			$response = $this->doRequest('POST', '/rest/shipment/v3/edi/labels/pdf', $options);
		} catch (Exception $e) {
			// TODO: Log error
			throw $e;

			return [];
		}

		return $response;
	}


	/**
	 * 
	 */
	public function getPDFLabelFromId(string $labelId, array $labelInfo): array
	{
		$defaults = [];
		$parameters = $this->mergeOptions($defaults, $labelInfo);
		$options['query'] = $parameters;
		$options['json'] = [['id' => $labelId]];

		try {
			$response = $this->doRequest('POST', '/rest/shipment/v3/labels/ids/pdf', $options);
		} catch (Exception $e) {
			// TODO: Log error
			throw $e;

			return [];
		}

		return $response;
	}


	// Just to get issuer code
	//Z11=PostNord Denmark, Z12=PostNord Sweden, Z13=PostNord Norway, Z14=PostNord Finland
	private function getIssuerCode(string $country): string
	{
		switch ($country) {
			case 'FI':
				return 'Z14';
				break;
			case 'NO':
				return 'Z13';
				break;
			case 'SE':
				return 'Z12';
				break;
			case 'DK':
				return 'Z11';
				break;
			default:
				return 'Z14';
				break;
		}
	}
	/**
	 * generate request body for booking
	 * 
	 * application info can be extract from somewhere/database?
	 * shipment info from $data
	 * consignor is the merchant
	 * consignee is customer. prestashop address object (customer address)
	 * deliveryParty is pick up point, from $data?
	 * 
	 */
	protected function generateBooking(string $customerEmail, $customerAddress, array $order, array $pickupAddress, array $shopAddress, string $country = 'FI'): array
	{
		$datetime = date(DATE_ISO8601);

		$body = [
			'messageDate' => $datetime,
			'messageFunction' => 'Instruction',
			'messageId' => uniqid(),
			'application' => [
				'name' => 'vg_postnord',
				'version' => '0.0.1'
			],
			'updateIndicator' => 'Original', //enum: Original, Update, Deletion
			'shipment' => [
				[
					'shipmentIdentification' => [
						'shipmentId' => '0' //from data?
					],
					'dateAndTimes' => [
						'loadingDate' => $datetime
					],
					'service' => [
						'basicServiceCode' => '19', //from data
						'additionalServiceCode' => ['A3', 'A7']
					],
					'freeText' => [], //from data
					'numberOfPackages' => [
						'value' => 1
					],
					'totalGrossWeight' => [
						'value' => 2, //from data
						'unit' => 'KGM'
					],
					'parties' => [
						'consignor' => [
							'issuerCode' => $this->getIssuerCode($shopAddress['shop_country']), //Z11=PostNord Denmark, Z12=PostNord Sweden, Z13=PostNord Norway, Z14=PostNord Finland
							'partyIdentification' => [
								'partyId' => $shopAddress['shop_party_id'],
								'partyIdType' => '160', //160=customer number,167=VAT customer number,156=Service point ID, 229=Geographic location
							],
							'party' => [
								'nameIdentification' => [
									'name' => $shopAddress['shop_name']
								],
								'address' => [
									'streets' => [$shopAddress['shop_street']],
									'postalCode' => $shopAddress['shop_postcode'],
									'city' => $shopAddress['shop_city'],
									'countryCode' => $shopAddress['shop_country']
								]
							]
						],
						'consignee' => [
							'issuerCode' => $this->getIssuerCode($country),
							'party' => [
								'nameIdentification' => [
									'name' => "{$customerAddress->firstname} {$customerAddress->lastname}"
								],
								'address' => [
									'streets' => ["{$customerAddress->address1} {$customerAddress->address2}"],
									'postalCode' => $customerAddress->postcode,
									'city' => $customerAddress->city,
									'countryCode' => $country
								],
								'contact' => [
									'contactName' => "{$customerAddress->firstname} {$customerAddress->lastname}",
									'emailAddress' => $customerEmail,
									'smsNo' => $customerAddress->phone
								]
							]
						],
						'deliveryParty' => [
							'partyIdentification' => [
								'partyId' => $pickupAddress['servicePointId'],
								'partyIdType' => '156', //160=customer number,167=VAT customer number,156=Service point ID, 229=Geographic location
							],
							'party' => [
								'nameIdentification' => [
									'name' => $pickupAddress['name']
								],
								'address' => [
									'streets' => ["{$pickupAddress['visitingAddress']['streetName']} {$pickupAddress['visitingAddress']['streetNumber']}"],
									'postalCode' => $pickupAddress['visitingAddress']['postalCode'],
									'city' => $pickupAddress['visitingAddress']['city'],
									'countryCode' => $pickupAddress['visitingAddress']['countryCode']
								]
							]
						]
					],
					"goodsItem" => [
						[
							'packageTypeCode' => 'PC',
							'items' => [
								[
									'itemIdentification' => [
										'itemId' => '0',
										'itemIdType' => 'SSCC', //SSCC for Nordic and DPD to other countries
									],
									'grossWeight' => [
										'value' => 2,
										'unit' => 'KGM'
									]
								]
							]
						]
					]
				]
			]
		];


		return $body;
	}
}
