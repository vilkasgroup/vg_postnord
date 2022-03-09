<?php

namespace Vilkas\Postnord\Service;

use Address;
use Carrier;
use Cart;
use Configuration;
use Country;
use Customer;
use Exception;
use Order;
use PrestaShopException;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

use Vilkas\Postnord\Client\PostnordClient;
use Vilkas\Postnord\Entity\VgPostnordBooking;
use Vilkas\Postnord\Entity\VgPostnordCartData;

class VgPostnordBookingService
{
    /** @var EntityManagerInterface */
    private $entityManager;

    /** @var PostnordClient */
    private $client;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;

        $this->client = new PostnordClient(
            Configuration::get('VG_POSTNORD_HOST'),
            Configuration::get('VG_POSTNORD_APIKEY')
        );
    }

    /**
     * Create a new 'blank' booking entity
     *
     * Grabs service point from cart data if it exists
     *
     * @throws Exception|ExceptionInterface
     */
    public function createBlankBooking(int $id_order): VgPostnordBooking
    {
        $cartDataRepository = $this->entityManager->getRepository(VgPostnordCartData::class);
        $bookingRepository  = $this->entityManager->getRepository(VgPostnordBooking::class);

        $booking = new VgPostnordBooking();
        $order   = new Order($id_order);

        // grab mandatory additional services from carrier settings
        $carrier_settings = json_decode(Configuration::get("VG_POSTNORD_CARRIER_SETTINGS"), true);
        if (
            array_key_exists($order->id_carrier, $carrier_settings)
            && array_key_exists("mandatory_service_codes", $carrier_settings[$order->id_carrier])
        ) {
            $mandatory_services = implode(",", $carrier_settings[$order->id_carrier]["mandatory_service_codes"]);
        } else {
            $mandatory_services = null;
        }

        /** @var VgPostnordBooking $previousBooking */
        $previousBooking = $bookingRepository->findOneBy(["id_order" => $id_order], ["id" => "DESC"]);

        /** @var VgPostnordCartData $cartData */
        $cartData = $cartDataRepository->findOneBy(["id_order" => $id_order]);
        if ($cartData) {
            $booking
                ->setCartData($cartData)
                ->setServicepointid($cartData->getServicePointId())
                ->setServicePointData($cartData->getServicePointData())
            ;
        } else {
            // if cart data doesn't exist, copy service point & data from previous shipment (if exists)
            if ($previousBooking) {
                $booking
                    ->setServicepointid($previousBooking->getServicePointId())
                    ->setServicePointData($previousBooking->getServicePointData())
                ;
            }
        }

        if ($previousBooking) {
            $booking
                ->setParcelData($previousBooking->getParcelData())
                ->setCustomsDeclaration($previousBooking->hasCustomsDeclaration())
                ->setCustomsDeclarationData($previousBooking->getCustomsDeclarationData())
                ->setDetailedDescription($previousBooking->getDetailedDescription());
            ;
        } else {
            $booking->setParcelData($this->_generateDefaultParcelData());
        }

        $booking
            ->setIdOrder($id_order)
            ->setAdditionalServices($mandatory_services)
        ;

        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        return $booking;
    }

    /**
     * @param VgPostnordBooking $booking
     *
     * @return VgPostnordBooking
     *
     * @throws PrestaShopException
     * @throws ExceptionInterface
     */
    public function sendBookingAndGenerateLabel(VgPostnordBooking $booking): VgPostnordBooking
    {
        $order    = new Order($booking->getIdOrder());
        $cart     = new Cart($order->id_cart);
        $customer = new Customer($cart->id_customer);
        $carrier  = new Carrier($order->id_carrier);

        $address_delivery = new Address($order->id_address_delivery);
        $customer_country = new Country($address_delivery->id_country);

        $carrier_settings = json_decode(Configuration::get("VG_POSTNORD_CARRIER_SETTINGS"), true);
        $service_code     = explode("_", $carrier_settings[$carrier->id]["service_code_consigneecountry"])[0];

        $additional_service_codes = explode(",", $booking->getAdditionalServices() ?? []);

        $items = json_decode($booking->getParcelData(), true) ?? [];
        $totalGrossWeight = 0;
        foreach ($items as &$item) {
            $item["id"] = "0";
            $totalGrossWeight += (float) $item["grossWeight"];
        }
        unset($item);

        $detailedDescription    = json_decode($booking->getDetailedDescription(), true) ?? [];
        $customsDeclarationData = json_decode($booking->getCustomsDeclarationData(), true) ?? [];

        $customsTotalGrossWeight = $customsTotalValue = 0;
        foreach ($detailedDescription as $dd) {
            $customsTotalGrossWeight += (float) $dd["grossWeight"];
            $customsTotalValue       += (float) $dd["value"];
        }

        $order_data = [
            "id"                      => "0", // should we generate this or let PostNord handle?
            "basicServiceCode"        => $service_code,
            "additionalServiceCode"   => $additional_service_codes,
            "numberOfPackages"        => count($items),
            "totalGrossWeight"        => $totalGrossWeight,
            "items"                   => $items,
            "hasCustomsDeclaration"   => $booking->hasCustomsDeclaration(),
            "customsDeclarationData"  => $customsDeclarationData,
            "detailedDescription"     => $detailedDescription,
            "EORINumber"              => Configuration::get("VG_POSTNORD_EORI_NUMBER"),
            "customsTotalGrossWeight" => $customsTotalGrossWeight,
            "customsTotalValue"       => $customsTotalValue,
            "postalCharge"            => (float) $order->total_shipping_tax_incl,
            "orderCurrency"           => \Currency::getIsoCodeById($order->id_currency)
        ];

        $shop_address  = json_decode(Configuration::get("VG_POSTNORD_SHOP_ADDRESS"), true);
        $return_address  = json_decode(Configuration::get("VG_POSTNORD_RETURN_ADDRESS"), true);
        $service_point = json_decode($booking->getServicePointData(), true);

        $pickup_address = [];
        // add service point data if related additional service is found
        if (in_array("A7", $additional_service_codes)) {
            $pickup_address = [
                "servicePointId" => $booking->getServicepointid(),
                "name" => $service_point["name"],
                "visitingAddress" => $service_point["visitingAddress"]
            ];
        }

        $label_info = [
            "paperSize" => "LABEL"
        ];

        $response = $this->client->createBooking(
            $customer->email,
            $address_delivery,
            $order_data,
            $shop_address,
            $return_address,
            $customer_country->iso_code,
            $label_info,
            $pickup_address
        );

        $bookingResponse = $response["bookingResponse"];
        $labelPrintout   = $response["labelPrintout"];

        // find all tracking urls in response
        $search_result = array_filter($bookingResponse["idInformation"][0]["urls"], function ($url) {
            return $url["type"] === "TRACKING";
        });
        $tracking_urls = array_column($search_result, "url") ?? null;

        $label_ids = $label_data = [];
        foreach ($labelPrintout as $lp) {
            $label_ids[]  = $lp["itemIds"][0]["itemIds"];
            $label_data[] = $lp["printout"]["data"];
        }

        $booking
            ->setIdBookingExternal($bookingResponse["bookingId"])
            ->setTrackingUrl(json_encode($tracking_urls, JSON_UNESCAPED_SLASHES))
            ->setIdLabelExternal(json_encode($label_ids))
            ->setLabelData(json_encode($label_data))
            ->setFinalized(new \DateTime())
        ;

        if (Configuration::get('VG_POSTNORD_FETCH_BOTH')) {
            $this->getReturnLabel($booking);
        }

        return $booking;
    }

    /**
     * @param VgPostnordBooking $booking
     *
     * @return VgPostnordBooking
     *
     * @throws ExceptionInterface
     */
    public function getReturnLabel(VgPostnordBooking $booking): VgPostnordBooking
    {
        $label_info = [
            "paperSize" => "LABEL"
        ];

        $itemIds = json_decode($booking->getIdLabelExternal(), true);

        foreach ($itemIds as $id) {
            $response = $this->client->getReturnPDFLabelFromId(
                $id,
                $label_info
            );
            $data = array_filter($response['labelPrintout'], function ($element) {
                return $element['printout']['labelFormat'] === 'PDF';
            });
            $returnLabel[] = $data[0]['printout']['data'];
        }
        if (!empty($returnLabel)) {
            $booking->setReturnLabelData(json_encode($returnLabel));
            $this->entityManager->flush();
        }

        return $booking;
    }

    private function _generateDefaultParcelData(): string
    {
        return json_encode(
            [
                [
                    "weight" => "",
                    "height" => "",
                    "width"  => "",
                    "length" => ""
                ]
            ]
        );
    }
}
