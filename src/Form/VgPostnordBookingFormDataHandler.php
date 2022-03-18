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

        // encode JSON-based fields back into JSON for storage
        $data["parcel_data"] = json_encode(array_values($data["parcel_data"]));
        $detailed_description = $data["customs_declaration"]["detailed_description"];
        if (!empty($detailed_description)) {
            $data["detailed_description"] = json_encode(array_values($detailed_description));
        }

        // grab customs declaration data and encode back into JSON for storage
        unset($data["customs_declaration"]["customs_declaration_data"]);
        unset($data["customs_declaration"]["detailed_description"]);
        $customs_declaration_data = [];
        foreach (array_keys($data["customs_declaration"]) as $key) {
            $customs_declaration_data[$key] = $data["customs_declaration"][$key];
        }
        ksort($customs_declaration_data);
        $data["customs_declaration_data"] = json_encode($customs_declaration_data);

        // store checkbox state as customs declaration
        $data["customs_declaration"] = $data["customs_declaration_checkbox"];

        $booking->fromArray($data);

        $this->entityManager->flush();

        return $booking->getId();
    }
}
