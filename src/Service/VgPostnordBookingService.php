<?php

namespace Vilkas\Postnord\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Vilkas\Postnord\Client\PostnordClient;
use Vilkas\Postnord\Entity\VgPostnordBooking;
use Vilkas\Postnord\Entity\VgPostnordCartData;
use Exception;
use Cart;
use Customer;
use Address;
use Order;
use Configuration;
use Country;
use PrestaShopException;
use Carrier;

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
        if (array_key_exists($order->id_carrier, $carrier_settings)
            && array_key_exists("mandatory_service_codes", $carrier_settings[$order->id_carrier])) {
            $mandatory_services = implode(",", $carrier_settings[$order->id_carrier]["mandatory_service_codes"]);
        } else {
            $mandatory_services = null;
        }


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
            /** @var VgPostnordBooking $previousBooking */
            $previousBooking = $bookingRepository->findOneBy(["id_order" => $id_order]);
            if ($previousBooking) {
                $booking
                    ->setServicepointid($previousBooking->getServicePointId())
                    ->setServicePointData($previousBooking->getServicePointData())
                ;
            }
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

        $address_invoice  = new Address($order->id_address_invoice);
        $customer_country = new Country($address_invoice->id_country);

        $carrier_settings = json_decode(Configuration::get("VG_POSTNORD_CARRIER_SETTINGS"), true);
        $service_code     = explode("_", $carrier_settings[$carrier->id]["service_code_consigneecountry"])[0];

        // TODO: id and grossWeight probably belong in a separate array
        $order_data = [
            "id"                    => "0", // TODO: I'm not sure what we should pass here, should we generate something based on id_order?
            "basicServiceCode"      => $service_code,
            "additionalServiceCode" => [],  // TODO: from booking
            "numberOfPackages"      => 1,   // TODO: from booking, probably need parcel "generator" similar to pakettikauppa
            "grossWeight"           => 1    // TODO: same as above
        ];

        $shop_address   = json_decode(Configuration::get("VG_POSTNORD_SHOP_ADDRESS"), true);
        $pickup_address = []; // I'm not sure when this would be needed

        $label_info = [
            "paperSize" => "LABEL"
        ];

        $response = $this->client->createBooking(
            $customer->email,
            $address_invoice,
            $order_data,
            $shop_address,
            $customer_country->iso_code,
            $label_info,
            $pickup_address
        );

        $bookingResponse = $response["bookingResponse"];
        $labelPrintout   = $response["labelPrintout"];

        $search_result = array_filter($bookingResponse["idInformation"][0]["urls"], function($url) {
            return $url["type"] === "TRACKING";
        });
        $tracking_url = $search_result[0] ?? null;

        $booking
            ->setIdBookingExternal($bookingResponse["bookingId"])
            ->setTrackingUrl($tracking_url["url"])
            ->setIdLabelExternal($labelPrintout[0]["itemIds"][0]["itemIds"]) // TODO: can this return an array of labels and ids?
            ->setLabelData($labelPrintout[0]["printout"]["data"])
            ->setFinalized(new \DateTime());

        $this->entityManager->flush();

        return $booking;
    }
}
