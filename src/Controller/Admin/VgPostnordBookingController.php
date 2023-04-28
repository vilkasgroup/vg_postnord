<?php

declare(strict_types=1);

namespace Vilkas\Postnord\Controller\Admin;

use Address;
use Carrier;
use Configuration;
use Country;
use Exception;
use Order;
use iio\libmergepdf\Driver\TcpdiDriver;
use iio\libmergepdf\Merger;
use Monolog\Logger;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use PrestaShopBundle\Security\Annotation\ModuleActivated;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Vg_postnord;
use Vilkas\Postnord\Client\PostnordClient;
use Vilkas\Postnord\Entity\VgPostnordBooking;
use Vilkas\Postnord\Grid\Filter\VgPostnordBookingQueryFilter;

/**
 * Class VgPostnordBookingController.
 *
 * @ModuleActivated(moduleName="vg_postnord", redirectRoute="admin_module_manage")
 */
class VgPostnordBookingController extends FrameworkBundleAdminController
{
    /**
     * @var Logger
     */
    private $logger;

    public function __construct()
    {
        parent::__construct();

        $this->logger = Vg_postnord::getLogger();
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

    public function editBookingAction(Request $request,  $bookingId): Response
    {
        $idBooking = (int) $bookingId;
        $repository = $this->get('vilkas.postnord.repository.vgpostnordbooking');
        $booking = $repository->findOneById($idBooking);
        $id_order = $booking->getIdOrder();

        $bookingFormBuilder = $this->get('vilkas.postnord.form.identifiable_object.builder.vg_postnord_booking_form_builder');
        $bookingForm = $bookingFormBuilder->getFormFor($idBooking);
        $bookingForm->handleRequest($request);

        $bookingFormHandler = $this->get('vilkas.postnord.form.identifiable_object.handler.vg_postnord_booking_form_handler');
        $result = $bookingFormHandler->handleFor($idBooking, $bookingForm);

        if ($result->isSubmitted() && $result->isValid()) {
            $this->addFlash('success', $this->trans('Successful modification.', 'Admin.Notifications.Success'));

            if ($request->get("save-and-go-to-order") !== null) {
                return $this->redirectToRoute("admin_orders_view", ["orderId" => $id_order]);
            }
            if ($request->get("save-and-stay") !== null) {
                return $this->redirectToRoute("admin_vg_postnord_edit_action", ["bookingId" => $idBooking]);
            }

            return $this->redirectToRoute('admin_vg_postnord_list_action');
        }

        return $this->render('@Modules/vg_postnord/views/templates/admin/edit-booking.html.twig', [
            'vgPostnordBookingEditForm' => $bookingForm->createView(),
            'ajaxurl' => $this->get('router')->generate('admin_vg_postnord_ajax_service_point_action'),
            'layoutTitle' => $this->trans('Edit Booking', 'Modules.Vgpostnord.Admin'),
            'layoutHeaderToolbarBtn' => $this->getToolbarButtons($id_order),
        ]);
    }

    /**
     * @AdminSecurity("is_granted(['create'], request.get('_legacy_controller'))", message="Access denied.")
     *
     * @param Request $request
     *
     * @return Response
     *
     * @throws Exception|ExceptionInterface
     */
    public function ajaxServicePointAction(Request $request): Response
    {
        $client = new PostnordClient(
            Configuration::get('VG_POSTNORD_HOST'),
            Configuration::get('VG_POSTNORD_APIKEY')
        );
        $carrierSetting = json_decode(Configuration::get('VG_POSTNORD_CARRIER_SETTINGS'), true);
        $service_codes = Vg_postnord::getCombinedServiceCodesForConfig($carrierSetting);
        $id_order = (int) $request->request->get('idOrder');
        $postalCode = $request->request->get('zipcode');

        $order = new Order($id_order);
        $carrier = new Carrier($order->id_carrier);
        $address = new Address($order->id_address_delivery);
        $countryIsoCode = Country::getIsoById($address->id_country);

        $params = [
            'countryCode' => $countryIsoCode,
            'agreementCountry' => $countryIsoCode,
            'postalCode' => $postalCode,
            'numberOfServicePoints' => 100, // TODO: this should probably be a setting?
            'typeId' => $carrierSetting[$carrier->id_reference]['service_codes'] // "type of the service point" or service code, see module configuration page
        ];

        try {
            $response = $client->getServicePointsByAddress($params, $service_codes);

            if (!empty($response['servicePoints'])) {
                $servicePoints = $response['servicePoints'];
                $servicePoints = array_reduce($servicePoints, function ($carry, $element) {
                    $carry[] = [
                        'servicePointId' => $element['servicePointId'],
                        'servicePointDetail' => "{$element['name']}. {$element['visitingAddress']['streetName']} {$element['visitingAddress']['streetNumber']}, {$element['visitingAddress']['postalCode']} {$element['visitingAddress']['city']}"
                    ];
                    return $carry;
                }, []);
                return $this->json($servicePoints);
            } else {
                return $this->returnErrorJsonResponse(
                    ['error' => $response['error']],
                    Response::HTTP_BAD_REQUEST
                );
            }
        } catch (Exception $e) {
            return $this->returnErrorJsonResponse(
                ['error' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

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
        try {
            $booking = $bookingService->createBlankBooking((int) $id_order);
        } catch (Exception $e) {
            $message = $this->trans("Could not create booking: %e%", "Modules.Vgpostnord.Admin", ["%e%" => $e->getMessage()]);
            $this->addFlash("error", $message);
            $this->logger->error("Could not create booking", ["exception" => $e]);
            return $this->redirectToRoute("admin_orders_view", ["orderId" => $id_order]);
        }

        if (!$generate_label) {
            $message = $this->trans("New booking created successfully", "Modules.Vgpostnord.Admin");
            $this->addFlash("success", $message);
            return $this->redirectToRoute("admin_vg_postnord_edit_action", ["bookingId" => $booking->getId()]);
        }

        try {
            $booking = $bookingService->sendBookingAndGenerateLabel($booking);
        } catch (\Throwable $e) {
            $this->addFlash("error", $e->getMessage());
            $this->logger->error("Error fetching label", ["exception" => $e]);
            return $this->redirectToRoute("admin_orders_view", ["orderId" => $id_order]);
        }

        if (Configuration::get('VG_POSTNORD_FETCH_BOTH')) {
            return $this->_getPDFBothLabelsResponse($booking);
        }

        return $this->_getPDFLabelResponse($booking);
    }

    /**
     * Fetch label for an existing (local) booking
     */
    public function sendBookingAction(Request $request): Response
    {
        $id_booking = (int) $request->get("id_booking");
        $booking = $this->_getBooking($id_booking);
        if (!$booking) {
            return $this->redirectToRoute("admin_orders_index");
        }

        // already fetched, just show the label
        if ($booking->isFinalized() && $booking->getLabelData() !== null) {
            return $this->_getPDFLabelResponse($booking);
        }

        $bookingService = $this->get("vilkas.postnord.service.vgpostnordbookingservice");

        try {
            $booking = $bookingService->sendBookingAndGenerateLabel($booking);
        } catch (\Throwable $e) {
            $this->addFlash("error", $e->getMessage());
            $this->logger->error("Error fetching label", ["exception" => $e]);
            return $this->redirectToRoute("admin_orders_view", ["orderId" => $booking->getIdOrder()]);
        }

        if (Configuration::get('VG_POSTNORD_FETCH_BOTH')) {
            return $this->_getPDFBothLabelsResponse($booking);
        }

        return $this->_getPDFLabelResponse($booking);
    }

    /**
     * Fetch return label for an existing (local) booking
     */
    public function getReturnLabelAction(Request $request): Response
    {
        $id_booking = (int) $request->get("id_booking");
        $booking = $this->_getBooking($id_booking);
        if (!$booking) {
            return $this->redirectToRoute("admin_orders_index");
        }

        // already fetched, just show the label
        if ($booking->isFinalized() && $booking->getReturnLabelData() !== null) {
            return $this->_getPDFLabelResponse($booking, true);
        }

        $bookingService = $this->get("vilkas.postnord.service.vgpostnordbookingservice");

        try {
            $booking = $bookingService->getReturnLabel($booking);
        } catch (\Throwable $e) {
            $this->addFlash("error", $e->getMessage());
            $this->logger->error("Error fetching return label", ["exception" => $e]);
            return $this->redirectToRoute("admin_orders_view", ["orderId" => $booking->getIdOrder()]);
        }

        return $this->_getPDFLabelResponse($booking, true);
    }

    /**
     * Merge and show both labels
     */
    public function getBothLabelAction(Request $request): Response
    {
        $id_booking = (int) $request->get("id_booking");
        $booking = $this->_getBooking($id_booking);
        if (!$booking) {
            return $this->redirectToRoute("admin_orders_index");
        }

        return $this->_getPDFBothLabelsResponse($booking);
    }

    /**
     * Combined ajax endpoint action function (so we only have to pass one route to the bulk action button)
     *
     * @param Request $request
     *
     * @return Response
     */
    public function ajaxFetchLabelAction(Request $request): Response
    {
        $action = $request->request->get("action");
        switch ($action) {
            case "fetch-label":
                return $this->_ajaxFetchLabel($request);
            case "combine-labels":
                return $this->_ajaxCombineLabels($request);
        }

        return $this->returnErrorJsonResponse(
            ["error" => "Invalid action"],
            Response::HTTP_BAD_REQUEST
        );
    }

    /**
     * Create a new booking and fetch label (for ajax request)
     *
     * @param Request $request
     *
     * @return Response
     */
    private function _ajaxFetchLabel(Request $request): Response
    {
        $id_order       = (int) $request->request->get("id_order");
        $bookingService = $this->get("vilkas.postnord.service.vgpostnordbookingservice");

        try {
            $booking = $bookingService->createBlankBooking($id_order);
            $booking = $bookingService->sendBookingAndGenerateLabel($booking);
        } catch (\Throwable $e) {
            $this->logger->error("Error fetching label (ajax)", [
                "exception" => $e,
                "id_order"  => $id_order
            ]);
            return $this->returnErrorJsonResponse(
                ["error" => $this->trans("Failed to fetch label for order with id %id_order%. Error: %e%", "Modules.Vgpostnord.Admin", ["%id_order%" => $id_order, "%e%" => $e->getMessage()])],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        $label_data = json_decode($booking->getLabelData(), true);
        if (empty($label_data)) {
            return $this->returnErrorJsonResponse(
                ["error" => $this->trans("No label data (order id %id_order%, booking id %id_booking%)", "Modules.Vgpostnord.Admin", ["%id_order%" => $id_order, "%id_booking%" => $booking->getId()])],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $this->json([
            "success"    => "Created booking and fetched label for order " . $id_order,
            "id_order"   => $id_order,
            "id_booking" => $booking->getId()
        ]);
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    private function _ajaxCombineLabels(Request $request): Response
    {
        $booking_ids       = array_map("intval", $request->request->get("booking_ids"));
        $bookingRepository = $this->get("vilkas.postnord.repository.vgpostnordbooking");

        $data = [];

        foreach ($booking_ids as $id_booking) {
            /** @var VgPostnordBooking $booking */
            $booking = $bookingRepository->findOneById($id_booking);
            if (!$booking) {
                // this effectively aborts the whole process if any ID is invalid, but that should be fine
                // since an invalid ID is a sign of a bigger problem
                $this->logger->error("Could not find booking", ["id_booking" => $id_booking]);
                return $this->returnErrorJsonResponse(
                    ["error" => $this->trans("Couldn't find booking with id %id_booking%", "Modules.Vgpostnord.Admin", ["%id_booking%" => $id_booking])],
                    Response::HTTP_INTERNAL_SERVER_ERROR
                );
            }
            $this->gatherBookingData($data, $booking);
        }

        if (!count($data)) {
            return $this->returnErrorJsonResponse(
                ["error" => $this->trans("No label data", "Modules.Vgpostnord.Admin")],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        $merger = new Merger(new TcpdiDriver());
        foreach ($data as $raw_label) {
            $merger->addRaw($raw_label);
        }
        $merged_raw_labels = $merger->merge();

        return $this->json([
            "success"    => "Successfully merged label data",
            "label_data" => base64_encode($merged_raw_labels)
        ]);
    }

    /**
     * Add data (if exists) from booking to given array
     *
     * @param array             $data    Array to add booking data to
     * @param VgPostnordBooking $booking
     *
     * @return void
     */
    private function gatherBookingData(array &$data, VgPostnordBooking $booking): void
    {
        if (empty($booking->getLabelData())) {
            return;
        }
        $label_data = json_decode($booking->getLabelData(), true);
        if (!$label_data) {
            return;
        }

        foreach ($label_data as $datum) {
            $data[] = base64_decode($datum);
        }

        if (empty($booking->getReturnLabelData())) {
            return;
        }
        $return_label_data = json_decode($booking->getReturnLabelData(), true);
        if (!$return_label_data) {
            return;
        }

        foreach ($return_label_data as $datum) {
            $data[] = base64_decode($datum);
        }
    }

    /**
     * Generate filename for label PDF
     */
    private function _getFileName(VgPostnordBooking $booking, $return = false): string
    {
        return $return ? "return" : "shipping"  . "_label_" . $booking->getIdOrder() . "_" . $booking->getId() . ".pdf";
    }

    /**
     * Generate raw PFD label data for either label (shipping or return) and return response
     */
    private function _getPDFLabelResponse(VgPostnordBooking $booking, $return = false): Response
    {
        if ($return) {
            if (!$booking->getReturnLabelData()) {
                $message = $this->trans("Booking is missing return label data. Something is wrong.", "Modules.Vgpostnord.Admin");
                $this->addFlash("error", $message);
                $this->logger->error("Booking is missing return label data", ["id_booking" => $booking->getId()]);
                return $this->redirectToRoute("admin_orders_view", ["orderId" => $booking->getIdOrder()]);
            }
            $filename = $this->_getFileName($booking, true);
            $label_data = json_decode($booking->getReturnLabelData(), true);
        } else {
            if (!$booking->getLabelData()) {
                $message = $this->trans("Booking is missing label data. Something is wrong.", "Modules.Vgpostnord.Admin");
                $this->addFlash("error", $message);
                $this->logger->error("Booking is missing label data", ["id_booking" => $booking->getId()]);
                return $this->redirectToRoute("admin_orders_view", ["orderId" => $booking->getIdOrder()]);
            }
            $filename = $this->_getFileName($booking);
            $label_data = json_decode($booking->getLabelData(), true);
        }

        if (count($label_data) > 1) {
            $merger = new Merger(new TcpdiDriver());
            foreach ($label_data as $datum) {
                $raw_label = base64_decode($datum);
                $merger->addRaw($raw_label);
            }
            $raw_label_data = $merger->merge();
        } else {
            $raw_label_data = base64_decode($label_data[0]);
        }

        return $this->_getRawPDFLabelResponse($raw_label_data, $filename);
    }

    /**
     * Generate raw PDF labels data for both labels (shipping and return) and return response
     */
    private function _getPDFBothLabelsResponse(VgPostnordBooking $booking): Response
    {
        if (!$booking->getLabelData()) {
            $message = $this->trans("No label data", "Modules.Vgpostnord.Admin");
            $this->addFlash("error", $message);
            $this->logger->error("No label data", ["id_booking" => $booking->getId()]);
            return $this->redirectToRoute("admin_orders_view", ["orderId" => (int) $booking->getIdOrder()]);
        }
        if (!$booking->getReturnLabelData()) {
            $message = $this->trans("No return label data", "Modules.Vgpostnord.Admin");
            $this->addFlash("error", $message);
            $this->logger->error("No return label data", ["id_booking" => $booking->getId()]);
            return $this->redirectToRoute("admin_orders_view", ["orderId" => (int) $booking->getIdOrder()]);
        }

        $data = [];
        $this->gatherBookingData($data, $booking);

        if (!empty($data)) {
            $merger = new Merger(new TcpdiDriver());
            foreach ($data as $value) {
                $merger->addRaw($value);
            }
            $mergedLabel = $merger->merge();
        } else {
            $message = $this->trans("No label data", "Modules.Vgpostnord.Admin");
            $this->addFlash("error", $message);
            $this->logger->error("No label data", ["id_booking" => $booking->getId()]);
            return $this->redirectToRoute("admin_orders_view", ["orderId" => (int) $booking->getIdOrder()]);
        }

        $filename = "labels_" . time() . ".pdf";

        return $this->_getRawPDFLabelResponse($mergedLabel, $filename);
    }

    /**
     * Generate raw PDF data response with required headers
     *
     * @param string $label_data  Raw label data
     * @param string $filename    Filename
     *
     * @return Response
     */
    private function _getRawPDFLabelResponse(string $label_data, string $filename): Response
    {
        return new Response(
            $label_data,
            Response::HTTP_OK,
            [
                "Content-Type"        => "application/pdf",
                "Content-Disposition" => "inline;filename=$filename"
            ]
        );
    }

    /**
     * Gets the header toolbar buttons.
     *
     * @param $id_order
     * @return array
     */
    private function getToolbarButtons($id_order): array
    {
        $toolbarButtons = [];
        $toolbarButtons['go_to_order'] = [
            'href' => $this->generateUrl('admin_orders_view', ["orderId" => $id_order]),
            'desc' => $this->trans('Go to Order', "Modules.Vgpostnord.Admin"),
            'icon' => 'arrow_back',
        ];
        return $toolbarButtons;
    }

    /**
     * Get booking from repository by id (and generate and show any errors)
     *
     * @param int|null $id_booking
     *
     * @return VgPostnordBooking|null Booking or null on error
     */
    private function _getBooking(?int $id_booking): ?VgPostnordBooking
    {
        if (!$id_booking) {
            $message = $this->trans("Missing id_booking in request. Something is wrong.",  "Modules.Vgpostnord.Admin");
            $this->addFlash("error", $message);
            $this->logger->error("Missing id_booking in request");

            return null;
        }

        $repository = $this->get("vilkas.postnord.repository.vgpostnordbooking");

        $booking = $repository->findOneBy(["id" => $id_booking], ["id" => "DESC"]);
        if (!$booking) {
            $message = $this->trans("Couldn't find booking with id %id_booking%", "Modules.Vgpostnord.Admin", ["%id_booking%" => $id_booking]);
            $this->addFlash("error", $message);
            $this->logger->error("Could not find booking", ["id_booking" => $id_booking]);

            return null;
        }

        return $booking;
    }
}
