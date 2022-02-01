<?php

class AdminPostnordBookingController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();

        $this->bootstrap = true;
        $this->table = 'vg_postnord_booking';

        $this->initList();
    }

    public function initList(): void
    {
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
}
