<?php declare(strict_types=1);
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
        if (!getenv('POSTNORD_HOST') || !getenv('POSTNORD_APIKEY')) {
            $this->markTestSkipped('POSTNORD_HOST and or POSTNORD_APIKEY environment variables are not set');
        }
        $this->client = new PostnordClient(getenv('POSTNORD_HOST'), getenv('POSTNORD_APIKEY'));
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
            'numberOfServicePoints'=> 1,
            'typeId' => 38 // TODO, figure out what this should be. 38 was found from a response without any type restrictions
        ];

        $results = $this->client->getServicePointsByAddress($params);

        // found service points
        $this->assertArrayHasKey('servicePoints', $results);
        // no idea what this should be used for
        $this->assertArrayHasKey('customerSupports', $results);

        // check the closest servicePoint country, it should be the same as above
        $firstPoint = $results['servicePoints'][0];

        var_dump($firstPoint);

        $this->assertEquals($params['countryCode'], $firstPoint['visitingAddress']['countryCode']);
    }

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
}
