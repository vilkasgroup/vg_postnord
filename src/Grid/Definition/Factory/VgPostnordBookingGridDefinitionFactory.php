<?php

declare(strict_types=1);

namespace Vilkas\Postnord\Grid\Definition\Factory;

use PrestaShop\PrestaShop\Core\Grid\Column\ColumnCollection;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\DataColumn;
use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\AbstractGridDefinitionFactory;

class VgPostnordBookingGridDefinitionFactory extends AbstractGridDefinitionFactory
{
    const GRID_ID = 'vgpostnordbooking';
    protected function getId()
    {
        return self::GRID_ID;
    }

    protected function getName()
    {
        return $this->trans('PostNord Booking', [], 'Modules.Vgpostnord.Admin');
    }

    protected function getColumns()
    {
        return (new ColumnCollection())
            ->add((new DataColumn('id_booking'))
                    ->setName($this->trans('Booking ID', [], 'Modules.Vgpostnord.Admin'))
                    ->setOptions([
                        'field' => 'id_booking',
                    ])
            )
            ->add((new DataColumn('tracking_url'))
                    ->setName($this->trans('Shipment Tracking URL', [], 'Modules.Vgpostnord.Admin'))
                    ->setOptions([
                        'field' => 'tracking_url',
                    ])
            )
            ->add((new DataColumn('servicepointid'))
                    ->setName($this->trans('Servicepoint ID', [], 'Modules.Vgpostnord.Admin'))
                    ->setOptions([
                        'field' => 'servicepointid',
                    ])
            )
            ->add((new DataColumn('additional_services'))
                    ->setName($this->trans('Additional Services', [], 'Modules.Vgpostnord.Admin'))
                    ->setOptions([
                        'field' => 'additional_services',
                    ])
            );
    }
}
