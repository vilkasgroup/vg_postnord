<?php

declare(strict_types=1);

namespace Vilkas\Postnord\Form;

use PrestaShop\PrestaShop\Core\Form\IdentifiableObject\DataProvider\FormDataProviderInterface;
use PrestaShopObjectNotFoundException;

use Vilkas\Postnord\Repository\VgPostnordBookingRepository;

final class VgPostnordBookingFormDataProvider implements FormDataProviderInterface
{
    private $repository;

    public function __construct(VgPostnordBookingRepository $repository)
    {
        $this->repository = $repository;

    }

    public function getData($bookingId)
    {
        $booking = $this->repository->findOneBy(['id'=>$bookingId]);
        if (empty($booking)) {
            throw new PrestaShopObjectNotFoundException('Object not found');
        }

        return $booking->toArray();
    }

    /**
     * Get default form data.
     *
     * @return mixed
     */
    public function getDefaultData()
    {
        return [
            'id_booking' => null,
            'tracking_url'=>'',
            'additional_services'=>''
        ];
    }
}
