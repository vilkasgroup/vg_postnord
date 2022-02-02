<?php

declare(strict_types=1);

namespace Vilkas\Postnord\Controller\Admin;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteria;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Vilkas\Postnord\Grid\Filter\VgPostnordBookingQueryFilter;

class VgPostnordBookingController extends FrameworkBundleAdminController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function indexAction(VgPostnordBookingQueryFilter $filters): Response
    {
        $gridFactory = $this->get('vilkas.postnord.grid.vg_postnord_booking_grid_factory');
        $grid = $gridFactory->getGrid($filters);

        return $this->render('@Modules/vg_postnord/views/templates/admin/booking-list.html.twig', [
            'vgPostnordBookingsGrid' => $this->presentGrid($grid)
        ]);
    }

    public function createBookingAction(Request $request): Response
    {
        return $this->redirectToRoute("admin_orders_index"); // TODO :)
    }

    public function bulkGenerateLabelAction(Request $request): Response
    {
        return $this->redirectToRoute("admin_orders_index"); // TODO :)
    }

    public function ajaxGenerateLabelAction(Request $request): JsonResponse
    {
        return $this->returnErrorJsonResponse(["YEP"], 200); // TODO :)
    }
}
