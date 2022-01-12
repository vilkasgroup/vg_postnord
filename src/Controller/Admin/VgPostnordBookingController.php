<?php

namespace Vilkas\Postnord\Controller\Admin;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class VgPostnordBookingController extends FrameworkBundleAdminController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function createBookingAction(Request $request): Response
    {
        return $this->redirectToRoute("admin_orders_index"); // TODO :)
    }
}
