<?php

declare(strict_types=1);

namespace Vilkas\Postnord\Form;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\ORMException;

use PrestaShop\PrestaShop\Core\Form\IdentifiableObject\DataHandler\FormDataHandlerInterface;

use Vilkas\Postnord\Entity\VgPostnordBooking;

final class VgPostnordBookingFormDataHandler implements FormDataHandlerInterface
{
    private $vgPostnordBookingRepository;
    private $entityManager;

    public function __construct(
        EntityRepository $vgPostnordBookingRepository,
        EntityManager $entityManager
        ) {
        $this->vgPostnordBookingRepository = $vgPostnordBookingRepository;
        $this->entityManager = $entityManager;
    }

    /**
     * TODO: Make sure to uncommented the necessary field.
     */
    public function create(array $data)
    {
        $booking = new VgPostnordBooking();
        $booking->fromArray($data);

        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        return $booking->getId();
    }

    /**
     * {@inheritdoc}
     *
     * @throws ContactException|ORMException
     */
    public function update($id, array $data): int
    {
        /** @var VgPostnordBooking $booking */
        $booking = $this->vgPostnordBookingRepository->findOneById((int) $id);

        // merge mandatory service codes from hidden inputs with additional services
        $data["additional_services"] = array_unique(array_merge($data["additional_services"], $data["mandatory_service_codes"]));

        $booking->fromArray($data);

        $this->entityManager->flush();

        return $booking->getId();
    }
}
