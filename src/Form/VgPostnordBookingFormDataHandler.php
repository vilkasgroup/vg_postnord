<?php

declare(strict_types=1);

namespace Vilkas\Postnord\Form;

use Doctrine\ORM\Query\Expr\Func;
use PrestaShop\PrestaShop\Core\Form\IdentifiableObject\DataHandler\FormDataHandlerInterface;
use Vilkas\Postnord\Entity\VgPostnordBooking;

final class VgPostnordBookingFormDataHandler implements FormDataHandlerInterface
{
    public function create(array $data)
    {
        $booking = new VgPostnordBooking();
        $booking->save();

        return $booking->id;
    }

    public function update($id, array $data)
    {
        $booking = new VgPostnordBooking((int) $id);
        $booking->tracking_url = $data['tracking_url'];
        $booking->update();
    }
}
