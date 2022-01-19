<?php

declare(strict_types=1);

namespace Tests\Postnord;

use PHPUnit\Framework\TestCase;
use Vilkas\Postnord\Client\PostnordClient;

class PostnordClientTest extends TestCase
{
	/**
	 * @var PostnordClient
	 */
	protected $client;

	/**
	 * Skip everything if environment variables are not available and the client cannot be setup
	 */
	protected function setUp(): void
	{
		$host = getenv('POSTNORD_HOST') ?: 'atapi2.postnord.com';

		if (!$host || !getenv('POSTNORD_APIKEY')) {
			$this->markTestSkipped('POSTNORD_HOST and or POSTNORD_APIKEY environment variables are not set');
		}
		$this->client = new PostnordClient($host, getenv('POSTNORD_APIKEY'));
	}

	/**
	 * test fetching some service points
	 */
	public function testGetServicePointsByAddress(): void
	{
		$params = [
			'countryCode' => 'FI',
			'agreementCountry' => 'FI',
			'city' => 'Tampere',
			'postalCode' => '33210',
			'streetName' => 'Finlaysoninkuja',
			'streetNumber' => '19',
			'numberOfServicePoints' => 1,
			'typeId' => 38, // typeId is the service code for pick up filter. 38 is "38 - Servicepoint (FIN)"
		];

		$results = $this->client->getServicePointsByAddress($params);

		// found service points
		$this->assertArrayHasKey('servicePoints', $results);
		// no idea what this should be used for
		$this->assertArrayHasKey('customerSupports', $results);

		// check the closest servicePoint country, it should be the same as above
		$firstPoint = $results['servicePoints'][0];

		// var_dump($firstPoint);

		$this->assertEquals($params['countryCode'], $firstPoint['visitingAddress']['countryCode']);
	}

	/**
	 * Test "empty" result for service points
	 */
	/*
    public function testGetServicePointsByAddressNoPoints(): void
    {
        $params = [
            'countryCode' => 'FI',
            'agreementCountry' => 'FI',
            'city' => 'nonexisting',
            'postalCode' => '99999',
            'streetName' => 'eivarmastiole',
            'streetNumber' => '19',
            'numberOfServicePoints'=> 1,
        ];

        $results = $this->client->getServicePointsByAddress($params);
        $this->assertArrayNotHasKey('servicePoints', $results);
    }
    */

	public function testGetBasicServiceCodes(): void
	{
		$params = [];

		$results = $this->client->getBasicServiceCodes($params);
		$this->assertArrayHasKey('data', $results);
	}

	public function testGetAdditionalServiceCodes(): void
	{
		$params = [];

		$results = $this->client->getAdditionalServiceCodes($params);
		$this->assertArrayHasKey('data', $results);
	}

	public function testGetValidCombinationsOfServiceCodes(): void
	{
		$params = [];

		$results = $this->client->getValidCombinationsOfServiceCodes($params);
		$this->assertArrayHasKey('data', $results);
	}

	public function testGetSurchargeHealthCheck(): void
	{
		$this->markTestSkipped('This errors out on their end all the time');
		$params = [];

		$results = $this->client->getSurchargeHealthCheck($params);
		$this->assertArrayHasKey('status', $results);
		$this->assertEquals('UP', $results['status']);
	}

	public function testCreateBooking(): void
	{
		$shopAddress = [
			'shop_name' => 'Temp Dev',
			'shop_party_id' => '1111111111',
			'shop_street' => 'Finlaysoninkuja 19',
			'shop_postcode' => '33210',
			'shop_city' => 'Tamperere',
			'shop_country' => 'FI'
		];
		$pickupAddress = [
			'name' => "Pn K-supermarket Kuninkaankulma",
			'servicePointId' => '9325',
			'visitingAddress' => [
				"countryCode" => "FI",
				"city" => "TAMPERE",
				"streetName" => "Kuninkaankatu",
				"streetNumber" => "14",
				"postalCode" => "33210",
				"additionalDescription" => NULL
			]
		];
		$email = 'customer@prestashop.com';
		$address = (object)[
			'firstname' => 'first',
			'lastname' => 'last',
			'address1' => 'Venuksenkuja 5',
			'address2' => 'M',
			'phone' => '0123456789',
			'postcode' => '01480',
			'city' => 'Vantaa',
			'country' => 'FI'
		];
		$order = [];
		$country = 'FI';
		$labelInfo = [
			'paperSize' => 'LABEL'
		];
		$results = $this->client->createBookingWithPDF($email, $address, $order, $pickupAddress, $shopAddress, $country, $labelInfo);
		var_dump($results);
		$this->assertArrayHasKey('bookingId', $results);
	}
}
