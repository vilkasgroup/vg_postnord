<?php
/**
 * @noinspection PhpUnitDeprecatedCallsIn10VersionInspection
 * @noinspection PhpUnreachableStatementInspection
 * @noinspection PhpUnhandledExceptionInspection
 */

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
     * @var array Shop address information
     */
    private $shopAddress = [
        'shop_name' => 'Temp Dev',
        'shop_party_id' => '1111111111',
        'shop_street' => 'Finlaysoninkuja 19',
        'shop_postcode' => '00100',
        'shop_city' => 'Helsinki',
        'shop_country' => 'FI',
        'shop_phone' => '+358123456789',
    ];

    /**
     * @var array Pickup point information
     *
     * TODO: What if this changes? Shouldn't this be fetched from PostNord DURING the tests?
     */
    private $pickupAddress = [
        'name' => 'Pn K-supermarket Kuninkaankulma',
        'servicePointId' => '9325',
        'visitingAddress' => [
            'countryCode' => 'FI',
            'city' => 'HELSINKI',
            'streetName' => 'Kuninkaankatu',
            'streetNumber' => '14',
            'postalCode' => '00100',
            'additionalDescription' => null,
        ],
    ];

    /**
     * @var string Customer email
     */
    private $email = 'customer@prestashop.com';

    /**
     * @var array Mimics a PrestaShop address object
     */
    private $address = [
        'firstname' => 'first',
        'lastname' => 'last',
        'address1' => 'Venuksenkuja 5',
        'address2' => 'M',
        'phone' => '0123456789',
        'postcode' => '01480',
        'city' => 'Vantaa',
        'country' => 'FI',
    ];

    /**
     * @var array Return address information
     */
    private $returnAddress = [
        'return_name' => 'Temp Dev',
        'return_street' => 'Finlaysoninkuja 19',
        'return_postcode' => '00100',
        'return_city' => 'Helsinki',
        'return_country' => 'FI',
    ];

    /**
     * @var string Customer country, default is 'FI'
     */
    private $country = 'FI';

    /**
     * @var array Order information
     */
    private $order = [
        'id' => '0',
        'basicServiceCode' => '19',
        'additionalServiceCode' => ['A3', 'A7'],
        'numberOfPackages' => 1,
        'totalGrossWeight' => 1.1,
        'items' => [
            [
                'id' => '0',
                'grossWeight' => 1.1,
            ],
        ],
        'hasCustomsDeclaration' => false,
        'reference' => 'ABCABCABC',
    ];

    /**
     * @var array Label settings
     */
    private $labelInfo = [
        'paperSize' => 'LABEL'
    ];

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
     * Test fetching some service points.
     */
    public function testGetServicePointsByAddress(): void
    {
        $this->markTestSkipped("Fails often due to not being able to find any service points");

        $params = [
            'countryCode' => 'FI',
            'agreementCountry' => 'FI',
            'city' => 'Helsinki',
            'postalCode' => '00100',
            'streetName' => 'Finlaysoninkuja',
            'streetNumber' => '19',
            'numberOfServicePoints' => 1,
            'typeId' => 38, // typeId is the service code for pickup filter. 38 is "38 - Servicepoint (FIN)"
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
        $this->markTestSkipped("Fails often due to not being able to find any service points");

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
        $results = $this->client->getBasicServiceCodes();
        $this->assertArrayHasKey('data', $results);
    }

    public function testGetAdditionalServiceCodes(): void
    {
        $results = $this->client->getAdditionalServiceCodes();
        $this->assertArrayHasKey('data', $results);
    }

    public function testGetValidCombinationsOfServiceCodes(): void
    {
        $results = $this->client->getValidCombinationsOfServiceCodes();
        $this->assertArrayHasKey('data', $results);
    }

    public function testGetSurchargeHealthCheck(): void
    {
        $this->markTestSkipped('This errors out on their end all the time');

        $results = $this->client->getSurchargeHealthCheck();
        $this->assertArrayHasKey('status', $results);
        $this->assertEquals('UP', $results['status']);
    }

    public function testCreateBooking(): void
    {
        // TODO: test with $order['hasCustomsDeclaration'] => true
        $results = $this->client->createBooking(
            $this->email,
            (object) $this->address,
            $this->order,
            $this->shopAddress,
            [],
            $this->country,
            [],
            $this->pickupAddress
        );

        $this->assertArrayHasKey('bookingId', $results);
        $this->assertArrayHasKey('value', $results['idInformation'][0]['ids'][0]);
        $this->assertRegExp('/\d{20}/m', $results['idInformation'][0]['ids'][0]['value']);
    }

    public function testCreateBookingWithPDF(): void
    {
        // TODO: test with $order['hasCustomsDeclaration'] => true
        $results = $this->client->createBooking(
            $this->email,
            (object) $this->address,
            $this->order,
            $this->shopAddress,
            [],
            $this->country,
            $this->labelInfo,
            $this->pickupAddress
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
        $itemId = '00364300432996662601';
        $results = $this->client->getReturnPDFLabelFromId($itemId, $this->labelInfo);
        $this->assertArrayHasKey('bookingResponse', $results);
    }

    public function testCreateBookingAndGetBothLabel(): void
    {
        // TODO: test with $order['hasCustomsDeclaration'] => true
        $order = $this->order;
        $order['items'][] = [
            'id' => '0',
            'grossWeight' => 1.1,
        ];
        $order['totalGrossWeight'] = 2.2;

        $results = $this->client->createBooking(
            $this->email,
            (object) $this->address,
            $order,
            $this->shopAddress,
            $this->returnAddress,
            $this->country,
            [],
            $this->pickupAddress
        );
        $this->assertArrayHasKey('bookingId', $results);

        $labelIds = [];
        foreach ($results["idInformation"][0]["ids"] as $id) {
            $labelIds[] = $id["value"];
        }

        $this->assertNotEmpty($labelIds, 'Missing label ids');

        // Postnord use the same id for item and label
        if (!empty($labelIds)) {
            $this->assertEquals(2, count($labelIds), 'Not enough label ids');
            foreach ($labelIds as $itemId) {
                $results = $this->client->getPDFLabelFromId($itemId, $this->labelInfo);
                $this->assertArrayHasKey('printout', $results[0]);
                $results = $this->client->getReturnPDFLabelFromId($itemId, $this->labelInfo);
                $this->assertArrayHasKey('bookingResponse', $results);
            }
        }
    }
}
