<?php

declare(strict_types=1);

namespace Vilkas\Postnord\Controller\Admin;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteria;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Vilkas\Postnord\Grid\Filter\VgPostnordBookingQueryFilter;
use Vilkas\Postnord\Form\Data\Provider\VgPostnordBookingFormDataProvider;

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

    public function editBookingAction(Request $request, $bookingId): Response
    {
        $bookingFormBuilder = $this->get('vilkas.postnord.form.identifiable_object.builder.vg_postnord_booking_form_builder');
        $bookingForm = $bookingFormBuilder->getFormFor((int) $bookingId);
        $bookingForm->handleRequest($request);

        $bookingFormHandler = $this->get('vilkas.postnord.form.identifiable_object.handler.vg_postnord_booking_form_handler');
        $result = $bookingFormHandler->handleFor($bookingId, $bookingForm);

        if (null !== $result->getIdentifiableObjectId()) {
            $this->addFlash('success', $this->trans('Successful creation.', 'Admin.Notifications.Success'));

            return $this->redirectToRoute('admin_vg_postnord_index_action');
        }


        return $this->render('@Modules/vg_postnord/views/templates/admin/edit-booking.html.twig', [
            'vgPostnordBookingEditForm' => $bookingForm->createView(),
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
