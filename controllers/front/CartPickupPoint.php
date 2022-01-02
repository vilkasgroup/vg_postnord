<?php declare(strict_types = 1);

use Vilkas\Postnord\Client\PostnordClient;
use Vilkas\Postnord\Entity\VgPostnordCartData;

class Vg_postnordCartPickupPointModuleFrontController extends ModuleFrontController
{
    private $client;
    private $issuerCountry;

    /**
     * Handles various actions indicated by $_POST['action'].
     *
     * @throws PrestaShopException
     */
    public function initContent()
    {
        $this->ajax = true;
        parent::initContent();

        $host = Configuration::get('VG_POSTNORD_HOST');
        $apikey = Configuration::get('VG_POSTNORD_APIKEY');

        $this->client = new PostnordClient($host, $apikey);
        $this->issuerCountry = Configuration::get('VG_POSTNORD_ISSUER_COUNTRY');
    }

    public function displayAjax()
    {
        $Cart = $this->context->cart;

        $action = Tools::getValue('action');

        if ($action === 'search') {
            try {
                $pickupPoints = $this->searchPickupPoints($Cart);
            } catch (Exception $e) {
                $this->renderResponse(['error' => $e->getMessage()], 500);
            }
            $this->renderResponse($pickupPoints, 200);
        } elseif ($action === 'save') {
            try {
                $servicepointid = Tools::getValue('servicePointId');
                $this->savePickupPoint($Cart, $servicepointid);
            } catch (Exception $e) {
                $this->renderResponse(['error' => $e->getMessage()], 500);
            }
            $this->renderResponse([], 200);
        } else {
            $this->renderResponse(['error' => 'invalid action'], 400);
        }
    }

    /**
     * Get carts postnord carrier settings
     * TODO: this should probably live in a service
     */
    private function getCarrierSettingsForCart(Cart $Cart): array
    {
        $id_carrier = $Cart->id_carrier;
        $Carrier = new Carrier($id_carrier);
        $id_carrier_reference = $Carrier->id_reference;
        $carrierSettings = $this->module->getCarrierConfigurations();

        return $carrierSettings[$id_carrier_reference];
    }

    /**
     * Search for pickup points with the cart address.
     *
     * Returns results directly from postnord api or an error
     *
     * throws when fails
     */
    private function searchPickupPoints(Cart $Cart): array
    {
        $id_address = $Cart->id_address_delivery;
        $Address = new Address($id_address);

        $carrierSettings = $this->getCarrierSettingsForCart($Cart);

        $typeId = $carrierSettings['service_codes'];

        $id_country = $Address->id_country;
        $Country = new Country($id_country);

        $postalCode = Tools::getValue('zipcode');

        $params = [
            'countryCode' => $Country->iso_code,
            'agreementCountry' => $Country->iso_code,
            //'city' => $Address->city,
            'postalCode' => $postalCode,
            //'streetName' => $Address->address1,
            //'streetNumber' => '19',
            'numberOfServicePoints' => 100, // TODO: this should probably be a setting?
            'typeId' => $typeId, // TODO, figure out what this should be. 38 was found from a response without any type restrictions
        ];

        $data =$this->client->getServicePointsByAddress($params);
        return $data;
    }


    /**
     * Save the selected pickup point to cart data
     */
    private function savePickupPoint(Cart $Cart, $servicepointid)
    {
        $manager = $this->get('doctrine.orm.entity_manager');
        $repo = $manager->getRepository(VgPostnordCartData::class);
        $repo->upsertCartServicePointId($Cart->id, $servicepointid);
    }

    /**
     * render response as json
     */
    private function renderResponse(array $data, int $statuscode=200)
    {
        http_response_code($statuscode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
