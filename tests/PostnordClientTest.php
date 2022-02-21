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
     * Skip everything if environment variables are not available and the client cannot be setup.
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
     * test fetching some service points.
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

        $this->assertEquals($params['countryCode'], $firstPoint['visitingAddress']['countryCode']);
    }

    /**
     * Test "empty" result for service points.
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

    public function testGetServicePointsById(): void
    {
        $params = [
            'countryCode' => 'FI',
            'ids' => '9335'
        ];

        $results = $this->client->getServicePointById($params);

        $this->assertArrayHasKey('name', $results);
        $this->assertEquals($params['ids'], $results['servicePointId']);
    }

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
        // extract from VG_POSTNORD_SHOP_ADDRESS
        $shopAddress = [
            'shop_name' => 'Temp Dev',
            'shop_party_id' => '1111111111',
            'shop_street' => 'Finlaysoninkuja 19',
            'shop_postcode' => '33210',
            'shop_city' => 'Tamperere',
            'shop_country' => 'FI',
        ];
        // Come from front office
        // The data follows the format from Postnord
        $pickupAddress = [
            'name' => 'Pn K-supermarket Kuninkaankulma',
            'servicePointId' => '9325',
            'visitingAddress' => [
                'countryCode' => 'FI',
                'city' => 'TAMPERE',
                'streetName' => 'Kuninkaankatu',
                'streetNumber' => '14',
                'postalCode' => '33210',
                'additionalDescription' => null,
            ],
        ];
        // extract from VG_POSTNORD_RETURN_ADDRESS can be left empty 
        // $returnAddress = [
        //     'return_name' => 'Temp Dev',
        //     'return_street' => 'Finlaysoninkuja 19',
        //     'return_postcode' => '33210',
        //     'return_city' => 'Tamperere',
        //     'return_country' => 'FI',
        // ];
        // just customer email
        $email = 'customer@prestashop.com';
        // address is prestashop address object (customer address)
        $address = (object) [
            'firstname' => 'first',
            'lastname' => 'last',
            'address1' => 'Venuksenkuja 5',
            'address2' => 'M',
            'phone' => '0123456789',
            'postcode' => '01480',
            'city' => 'Vantaa',
            'country' => 'FI',
        ];
        // It would be nice if the $order follow this format
        $order = [
            'id' => '0',
            'basicServiceCode' => '19',
            'additionalServiceCode' => ['A3', 'A7'],
            'numberOfPackages' => 1,
            'grossWeight' => 1.1,
            // 'itemId'=>'Maybe same as shipmentId?'
        ];
        // Customer country, default is 'FI'
        $country = 'FI';
        // check $labelInfo format in Post Nord documentation
        // $labelInfo = [
        // 'paperSize' => 'LABEL'
        // ];
        $results = $this->client->createBooking(
            $email,
            $address,
            $order,
            $shopAddress,
            [],
            $country,
            [],
            $pickupAddress
        );
        $this->assertArrayHasKey('bookingId', $results);
        $this->assertArrayHasKey('value', $results['idInformation'][0]['ids'][0]);
        $this->assertRegExp('/\d{20}/m', $results['idInformation'][0]['ids'][0]['value']);
    }

    public function testCreateBookingWithPDF(): void
    {
        $shopAddress = [
            'shop_name' => 'Temp Dev',
            'shop_party_id' => '1111111111',
            'shop_street' => 'Finlaysoninkuja 19',
            'shop_postcode' => '33210',
            'shop_city' => 'Tamperere',
            'shop_country' => 'FI',
        ];
        $pickupAddress = [
            'name' => 'Pn K-supermarket Kuninkaankulma',
            'servicePointId' => '9325',
            'visitingAddress' => [
                'countryCode' => 'FI',
                'city' => 'TAMPERE',
                'streetName' => 'Kuninkaankatu',
                'streetNumber' => '14',
                'postalCode' => '33210',
                'additionalDescription' => null,
            ],
        ];
        $email = 'customer@prestashop.com';
        $address = (object) [
            'firstname' => 'first',
            'lastname' => 'last',
            'address1' => 'Venuksenkuja 5',
            'address2' => 'M',
            'phone' => '0123456789',
            'postcode' => '01480',
            'city' => 'Vantaa',
            'country' => 'FI',
        ];
        $order = [
            'id' => '0',
            'basicServiceCode' => '19',
            'additionalServiceCode' => ['A3', 'A7'],
            'numberOfPackages' => 1,
            'grossWeight' => 1.1,
            // 'itemId'=>'Maybe same as shipmentId?'
        ];
        $country = 'FI';
        // check $labelInfo format in Post Nord documentation
        $labelInfo = [
            'paperSize' => 'LABEL',
        ];
        $results = $this->client->createBooking(
            $email,
            $address,
            $order,
            $shopAddress,
            [],
            $country,
            $labelInfo,
            $pickupAddress
        );
        $this->assertArrayHasKey('labelPrintout', $results);
    }

    public function testGetPDFLabelFromId(): void
    {
        $labelInfo = [
            'paperSize' => 'LABEL',
        ];
        $labelId = '00364300432996651506';
        $results = $this->client->getPDFLabelFromId($labelId, $labelInfo);
        $this->assertArrayHasKey('printout', $results[0]);
    }


    public function testGetReturnPDFLabelFromId(): void
    {
        $labelInfo = [
            'paperSize' => 'LABEL',
        ];
        $itemId = '00364300432996662601';
        $results = $this->client->getReturnPDFLabelFromId($itemId, $labelInfo);
        $this->assertArrayHasKey('bookingResponse', $results);
    }

    public function testCreateBookingAndGetBothLabel(): void
    {
        // extract from VG_POSTNORD_SHOP_ADDRESS
        $shopAddress = [
            'shop_name' => 'Temp Dev',
            'shop_party_id' => '1111111111',
            'shop_street' => 'Finlaysoninkuja 19',
            'shop_postcode' => '33210',
            'shop_city' => 'Tamperere',
            'shop_country' => 'FI',
        ];
        // extract from VG_POSTNORD_RETURN_ADDRESS
        $returnAddress = [
            'return_name' => 'Temp Dev',
            'return_street' => 'Finlaysoninkuja 19',
            'return_postcode' => '33210',
            'return_city' => 'Tamperere',
            'return_country' => 'FI',
        ];
        // Come from front office
        // The data follows the format from Postnord
        $pickupAddress = [
            'name' => 'Pn K-supermarket Kuninkaankulma',
            'servicePointId' => '9325',
            'visitingAddress' => [
                'countryCode' => 'FI',
                'city' => 'TAMPERE',
                'streetName' => 'Kuninkaankatu',
                'streetNumber' => '14',
                'postalCode' => '33210',
                'additionalDescription' => null,
            ],
        ];
        // just customer email
        $email = 'customer@prestashop.com';
        // address is prestashop address object (customer address)
        $address = (object) [
            'firstname' => 'first',
            'lastname' => 'last',
            'address1' => 'Venuksenkuja 5',
            'address2' => 'M',
            'phone' => '0123456789',
            'postcode' => '01480',
            'city' => 'Vantaa',
            'country' => 'FI',
        ];
        // It would be nice if the $order follow this format
        $order = [
            'id' => '0',
            'basicServiceCode' => '19',
            'additionalServiceCode' => ['A3', 'A7'],
            'numberOfPackages' => 1,
            'grossWeight' => 1.1,
            // 'itemId'=>'Maybe same as shipmentId?'
        ];
        // Customer country, default is 'FI'
        $country = 'FI';
        // check $labelInfo format in Post Nord documentation
        $labelInfo = [
            'paperSize' => 'LABEL'
        ];
        $results = $this->client->createBooking(
            $email,
            $address,
            $order,
            $shopAddress,
            $returnAddress,
            $country,
            [],
            $pickupAddress
        );
        $this->assertArrayHasKey('bookingId', $results);

        // Postnord use the same id for item and label
        $itemId = $results['idInformation'][0]['ids'][0]['value'];
        $results = $this->client->getPDFLabelFromId($itemId, $labelInfo);
        $this->assertArrayHasKey('printout', $results[0]);
        $results = $this->client->getReturnPDFLabelFromId($itemId, $labelInfo);
        $this->assertArrayHasKey('bookingResponse', $results);
    }
}
