<?php

declare(strict_types=1);

namespace Vilkas\Postnord\Form;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Func;
use PrestaShop\PrestaShop\Core\CommandBus\CommandBusInterface;
use PrestaShop\PrestaShop\Core\Form\IdentifiableObject\DataHandler\FormDataHandlerInterface;
use Vilkas\Postnord\Entity\VgPostnordBooking;

final class VgPostnordBookingFormDataHandler implements FormDataHandlerInterface
{
    private $vgPostnordBookingRepository;
    private $entityMananger;

    public function __construct(
        EntityRepository $vgPostnordBookingRepository,
        EntityManager $entityManager
    )
    {
        $this->entityMananger = $entityManager;
        $this->vgPostnordBookingRepository = $vgPostnordBookingRepository;
    }

    public function create(array $data)
    {
        $booking = new VgPostnordBooking();
        $booking->save();

        return $booking->id;
    }

    /**
     * {@inheritdoc}
     *
     * @throws ContactException
     */
    public function update($id, array $data)
    {
        $booking = new VgPostnordBooking((int) $id);
        $booking->tracking_url = $data['tracking_url'];
        

        // $editBookingCommand = (new )
        $booking->update();
    }
}
