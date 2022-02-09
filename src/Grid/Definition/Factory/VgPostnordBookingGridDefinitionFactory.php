<?php

declare(strict_types=1);

namespace Vilkas\Postnord\Grid\Definition\Factory;

use PrestaShop\PrestaShop\Core\Grid\Action\Row\RowActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\Type\LinkRowAction;
use PrestaShop\PrestaShop\Core\Grid\Column\ColumnCollection;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ActionColumn;
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
            )
            ->add((new ActionColumn('action'))
                ->setName($this->trans('Action', [], 'Module.Vgpostnord.Admin'))
                ->setOptions([
                    'actions'=>$this->getRowActions()
                ]));
    }

    protected function getRowActions(): RowActionCollection
    {
        return (new RowActionCollection())
            ->add(
                (new LinkRowAction('edit'))
                    ->setName($this->trans('Edit', [], 'Modules.Vgpostnord.Admin'))
                    ->setIcon('edit')
                    ->setOptions([
                        'route' => 'admin_vg_postnord_edit_action',
                        'route_param_name' => 'bookingId',
                        'route_param_field' => 'id_booking',
                        'clickable_row' => true,
                    ])

            );
    }
}
