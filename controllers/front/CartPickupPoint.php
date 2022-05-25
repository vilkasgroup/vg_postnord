<?php

declare(strict_types=1);

use Vilkas\Postnord\Client\PostnordClient;
use Vilkas\Postnord\Entity\VgPostnordCartData;
use Vilkas\Postnord\Repository\VgPostnordCartDataRepository;

class Vg_postnordCartPickupPointModuleFrontController extends ModuleFrontController
{
    /** @var PostnordClient */
    private $client;

    private $issuerCountry;

    /**
     * Handles various actions indicated by $_POST['action'].
     *
     * @throws Exception
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
     * Search for pickup points with the cart address.
     *
     * Returns results directly from postnord api or an error
     *
     * @throws Exception when fails
     */
    private function searchPickupPoints(Cart $Cart): array
    {
        $id_address = $Cart->id_address_delivery;
        $Address = new Address($id_address);

        $id_carrier_reference = Tools::getValue('carrierIdReference');
        $carrierSettings = $this->module->getCarrierConfiguration($id_carrier_reference);

        $typeId = $carrierSettings['service_codes'];

        $id_country = $Address->id_country;
        $postalCode = Tools::getValue('zipcode');

        $countryIsoCode = Country::getIsoById($id_country);

        $params = [
            'countryCode' => $countryIsoCode,
            'agreementCountry' => $countryIsoCode,
            //'city' => $Address->city,
            'postalCode' => $postalCode,
            //'streetName' => $Address->address1,
            //'streetNumber' => '19',
            'numberOfServicePoints' => 100, // TODO: this should probably be a setting?
            'typeId' => $typeId // "type of the service point" or service code, see module configuration page
        ];

        return $this->client->getServicePointsByAddress($params);
    }

    /**
     * Save the selected pickup point to cart data
     */
    private function savePickupPoint(Cart $Cart, $servicepointid)
    {
        $manager = $this->get('doctrine.orm.entity_manager');
        /** @var VgPostnordCartDataRepository $repo */
        $repo = $manager->getRepository(VgPostnordCartData::class);
        $repo->upsertCartServicePointId($Cart->id, $servicepointid);
    }

    /**
     * render response as json
     */
    private function renderResponse(array $data, int $status_code = 200)
    {
        http_response_code($status_code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
