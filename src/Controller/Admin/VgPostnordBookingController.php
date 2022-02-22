<?php

declare(strict_types=1);

namespace Vilkas\Postnord\Controller\Admin;

use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteria;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use PrestaShopBundle\Security\Annotation\ModuleActivated;

use iio\libmergepdf\Merger;
use iio\libmergepdf\Driver\TcpdiDriver;

use Vilkas\Postnord\Entity\VgPostnordBooking;
use Vilkas\Postnord\Grid\Filter\VgPostnordBookingQueryFilter;
use Vilkas\Postnord\Form\Data\Provider\VgPostnordBookingFormDataProvider;

/**
 * Class VgPostnordBookingController.
 *
 * @ModuleActivated(moduleName="vg_postnord", redirectRoute="admin_module_manage")
 */


class VgPostnordBookingController extends FrameworkBundleAdminController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     *
     * @param VgPostnordBookingQueryFilter $filters)
     *
     * @return Response
     */
    public function listAction(VgPostnordBookingQueryFilter $filters): Response
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
        $result = $bookingFormHandler->handleFor((int) $bookingId, $bookingForm);

        if ($result->isSubmitted() && $result->isValid()) {
            $this->addFlash('success', $this->trans('Successful modification.', 'Admin.Notifications.Success'));

            return $this->redirectToRoute('admin_vg_postnord_list_action');
        }


        return $this->render('@Modules/vg_postnord/views/templates/admin/edit-booking.html.twig', [
            'vgPostnordBookingEditForm' => $bookingForm->createView(),
        ]);
    }

    // TODO: logging, I guess

    /**
     * Create a new booking, and fetch label if specified (generate_label = true)
     */
    public function createBookingAction(Request $request): Response
    {
        $id_order = $request->get("id_order");
        if (!$id_order) {
            $message = $this->trans("Missing id_order in request. Something is wrong.", "Modules.Vgpostnord.Admin");
            $this->addFlash("error", $message);
            return $this->redirectToRoute("admin_orders_index");
        }
        $generate_label = $request->get("generate_label");

        $bookingService = $this->get("vilkas.postnord.service.vgpostnordbookingservice");
        $booking = $bookingService->createBlankBooking((int) $id_order);
        // TODO: the above function can probably throw something

        if (!$generate_label) {
            $message = $this->trans("New booking created successfully", "Modules.Vgpostnord.Admin");
            $this->addFlash("success", $message);
            return $this->redirectToRoute("admin_orders_view", ["orderId" => $id_order]);
        }

        try {
            $booking = $bookingService->sendBookingAndGenerateLabel($booking);
        } catch (\Throwable $e) {
            $this->addFlash("error", $e->getMessage());
            return $this->redirectToRoute("admin_orders_view", ["orderId" => $id_order]);
        }

        return $this->_getPDFLabelResponse($booking);
    }

    /**
     * Fetch label for an existing (local) booking
     */
    public function sendBookingAction(Request $request): Response
    {
        $id_booking = $request->get("id_booking");
        if (!$id_booking) {
            $message = $this->trans("Missing id_booking in request. Something is wrong.",  "Modules.Vgpostnord.Admin");
            $this->addFlash("error", $message);
            return $this->redirectToRoute("admin_orders_index");
        }

        /** @var EntityManager $entityManager */
        $entityManager = $this->container->get('doctrine.orm.entity_manager');
        $repository = $entityManager->getRepository(VgPostnordBooking::class);

        $booking = $repository->findOneBy(["id" => $id_booking]);
        if (!$booking) {
            $message = $this->trans("Could not find booking with id $id_booking", "Modules.Vgpostnord.Admin");
            $this->addFlash("error", $message);
            return $this->redirectToRoute("admin_orders_index");
        }

        if ($booking->getFinalized()) {
            return $this->_getPDFLabelResponse($booking);
        }

        $bookingService = $this->get("vilkas.postnord.service.vgpostnordbookingservice");

        try {
            $booking = $bookingService->sendBookingAndGenerateLabel($booking);
        } catch (\Throwable $e) {
            $this->addFlash("error", $e->getMessage());
            return $this->redirectToRoute("admin_orders_view", ["orderId" => $booking->getIdOrder()]);
        }

        return $this->_getPDFLabelResponse($booking);
    }

    /**
     * Create bookings and fetch labels for orders in bulk
     */
    public function bulkFetchLabelAction(Request $request): Response
    {
        $ids = $request->request->get("order_orders_bulk");
        $bookingService = $this->get("vilkas.postnord.service.vgpostnordbookingservice");

        $data = [];

        foreach ($ids as $id_order) {
            try {
                $booking = $bookingService->createBlankBooking((int) $id_order);
                $booking = $bookingService->sendBookingAndGenerateLabel($booking);
            } catch (\Throwable $e) {
                $this->addFlash("error", $e->getMessage());
                continue;
            }

            $label_data = $booking->getLabelData();
            if ($label_data) {
                $data[] = base64_decode($label_data);
            }
        }

        if (!count($data)) {
            $message = $this->trans("No label data", "Modules.Vgpostnord.Admin");
            $this->addFlash("error", $message);
            return $this->redirectToRoute("admin_orders_index");
        }

        $merger = new Merger(new TcpdiDriver());
        foreach ($data as $raw_label) {
            $merger->addRaw($raw_label);
        }
        $merged_raw_labels = $merger->merge();

        $filename = "labels_" . time() . ".pdf";

        return new Response(
            $merged_raw_labels,
            200,
            [
                "Content-Type"        => "application/pdf",
                "Content-Disposition" => "inline;filename=$filename"
            ]
        );
    }

    /**
     * Generate filename for label PDF
     */
    private function _getFileName(VgPostnordBooking $booking, $return = false): string
    {
        return $return ? "return" : "shipping"  . "_label_" . $booking->getIdOrder() . "_" . $booking->getId() . ".pdf";
    }

    /**
     * Generate raw PFD label data response with related headers
     */
    private function _getPDFLabelResponse(VgPostnordBooking $booking): Response
    {
        if (!$booking->getLabelData()) {
            $message = $this->trans("Booking is missing label data. Something is wrong.", "Modules.Vgpostnord.Admin");
            $this->addFlash("error", $message);
            $this->redirectToRoute("admin_orders_view", ["orderId" => $booking->getIdOrder()]);
        }

        $filename = $this->_getFileName($booking);

        return new Response(
            base64_decode($booking->getLabelData()),
            200,
            [
                "Content-Type"        => "application/pdf",
                "Content-Disposition" => "inline;filename=$filename"
            ]
        );
    }

    /**
     * Generate raw PFD return label data response with related headers
     */
    private function _getPDFReturnLabelResponse(VgPostnordBooking $booking): Response
    {
        if (!$booking->getReturnLabelData()) {
            $message = $this->trans("Booking is missing return label data. Something is wrong.", "Modules.Vgpostnord.Admin");
            $this->addFlash("error", $message);
            $this->redirectToRoute("admin_orders_view", ["orderId" => $booking->getIdOrder()]);
        }

        $filename = $this->_getFileName($booking, true);

        return new Response(
            base64_decode($booking->getReturnLabelData()),
            200,
            [
                "Content-Type"        => "application/pdf",
                "Content-Disposition" => "inline;filename=$filename"
            ]
        );
    }
}
