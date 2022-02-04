<?php

declare(strict_types=1);

namespace Vilkas\Postnord\Form;

use PrestaShop\PrestaShop\Core\Form\IdentifiableObject\DataProvider\FormDataProviderInterface;
use PrestaShopObjectNotFoundException;

use Vilkas\Postnord\Entity\VgPostnordBooking;

final class VgPostnordBookingFormDataProvider implements FormDataProviderInterface
{
    // EntityRepository $repository
    public function __construct()
    {
        // $this->repository = $repository;
    }

    public function getData($bookingId)
    {
        // $entityManager = $this->container->get('doctrine.orm.entity_manager');
        // $VgPostnordBookingRepository = $entityManager->getRepository(VgPostnordBooking::class);

        // $booking = $VgPostnordBookingRepository->findByBookingId($bookingId);

        $booking = new VgPostnordBooking($bookingId);

        if (empty($booking->getId())) {
            throw new PrestaShopObjectNotFoundException('Object not found');
        }

        return [
            'id_booking' => $booking->getId(),
            'tracking_url'=>$booking->getTrackingUrl(),
            'additional_services'=>[1]
        ];
    }

    /**
     * Get default form data.
     *
     * @return mixed
     */
    public function getDefaultData()
    {
        return [
            'id_booking' => 1,
            'tracking_url'=>'',
            'additional_services'=>''
        ];
    }
}
