<?php

declare(strict_types=1);

use Vilkas\Postnord\Entity\VgPostnordBooking;

class AdminPostnordBookingController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();

        $this->bootstrap = true;
        $this->table = 'vg_postnord_booking';
        $this->_defaultOrderBy = 'id_booking';
        $this->identifier = 'id_booking';
        $this->className = VgPostnordBooking::class;
        $this->initList();
        $this->initForm();
    }


    public function initList(): void
    {
        $this->addRowAction('view');
        $this->addRowAction('edit');
        $this->addRowAction('delete');
        $this->fields_list = [
            'id_booking' => [
                'title' => $this->trans('Booking ID', [], 'Modules.Vgpostnord.Admin'),
                'width' => 'auto'
            ],
            'tracking_url' => [
                'title' => $this->trans('Tracking URL', [], 'Modules.Vgpostnord.Admin'),
                'width' => 'auto'
            ],
            'servicepointid' => [
                'title' => $this->trans('Servicepoint ID', [], 'Modules.Vgpostnord.Admin'),
                'width' => 'auto'
            ],
            'additional_services' => [
                'title' => $this->trans('Additional Services', [], 'Modules.Vgpostnord.Admin'),
                'width' => 'auto'
            ],
        ];
    }

    public function initForm(): void
    {
        $this->fields_form = [
            'legend' => [
                'title' => $this->trans('Postnord booking', [], 'Modules.Vgpostnord.Admin')
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->trans('Tracking URL', [], 'Modules.Vgpostnord.Admin'),
                    'name' => 'tracking_url',
                    'required' => false,
                    'lang' => false
                ],
                [
                    'type' => 'text',
                    'label' => $this->trans('Service Point', [], 'Modules.Vgpostnord.Admin'),
                    'name' => 'servicepointid',
                    'required' => false,
                    'lang' => false
                ],
                [
                    'type' => 'checkbox',
                    'label' => $this->trans('Additional Services', [], 'Modules.Vgpostnord.Admin'),
                    'name' => 'additional_services',
                    'required' => false,
                    'lang' => false,
                    'values' => [
                        'query' => [
                            [
                                'additionalServiceCode' => 'A1',
                                'label' => 'a one'
                            ],
                            [
                                'additionalServiceCode' => 'A2',
                                'label' => 'a two'
                            ],
                            [
                                'additionalServiceCode' => 'A3',
                                'label' => 'a three'
                            ],
                            [
                                'additionalServiceCode' => 'A4',
                                'label' => 'a four'
                            ],
                        ],
                        'id' => 'additionalServiceCode',
                        'name' => 'label'
                    ]
                ],
            ],
            'submit' => [
                'title' => $this->trans('Save', [], 'Modules.Vgpostnord.Admin')
            ]
        ];
        $id = Tools::getValue('id_booking');
        $booking = new VgPostnordBooking($id);
        foreach (explode(', ', $booking->additional_services ?? '') as $key => $value) {
            $this->fields_value["additional_services_{$value}"] = true;
        }
    }
    public function postProcess(): void
    {
        if (($this->action === 'save') && ($this->id_object)) { // Update existing
            $additional_services = [];
            for ($i = 1; $i <= 4; $i++) {
                if (Tools::getValue('additional_services_A' . (int)$i)) {
                    array_push($additional_services, 'A' . (int)$i);
                }
            }
            $id = Tools::getValue('id_booking');
            $booking = new VgPostnordBooking($id);
            if (Tools::getValue('tracking_url')) {
                $booking->tracking_url = Tools::getValue('tracking_url');
            }
            if (Tools::getValue('servicepointid')) {
                $booking->servicepointid = Tools::getValue('servicepointid');
            }
            $booking->additional_services = implode(', ', $additional_services);
            $booking->update();
            return;
        }
    }
}
