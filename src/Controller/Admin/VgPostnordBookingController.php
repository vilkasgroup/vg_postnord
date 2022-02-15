<?php

declare(strict_types=1);

namespace Vilkas\Postnord\Controller\Admin;

use Context;
use Exception;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use PrestaShop\PrestaShop\Adapter\Entity\Address;
use PrestaShop\PrestaShop\Adapter\Entity\Configuration;
use PrestaShop\PrestaShop\Adapter\Entity\Country;
use PrestaShop\PrestaShop\Adapter\Entity\Order;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteria;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use PrestaShopBundle\Security\Annotation\ModuleActivated;

use iio\libmergepdf\Merger;
use iio\libmergepdf\Driver\TcpdiDriver;
use PrestaShop\PrestaShop\Adapter\Entity\Db;
use PrestaShop\PrestaShop\Adapter\Entity\DbQuery;
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
     * 
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
        $dbQuery = new DbQuery();
        $dbQuery->select('id_order')
            ->from('vg_postnord_booking', 'b')
            ->where("b.id_booking = {$idBooking}");
        $idOrder = (int) (Db::getInstance()->executeS($dbQuery))[0]['id_order'];
        $bookingFormBuilder = $this->get('vilkas.postnord.form.identifiable_object.builder.vg_postnord_booking_form_builder');
        $bookingForm = $bookingFormBuilder->getFormFor($idBooking);
        $bookingForm->handleRequest($request);
        $bookingFormHandler = $this->get('vilkas.postnord.form.identifiable_object.handler.vg_postnord_booking_form_handler');
        $result = $bookingFormHandler->handleFor($idBooking, $bookingForm);

        if ($result->isSubmitted() && $result->isValid()) {
            $this->addFlash('success', $this->trans('Successful modification.', 'Admin.Notifications.Success'));

            return $this->redirectToRoute('admin_vg_postnord_list_action');
        }

        return $this->render('@Modules/vg_postnord/views/templates/admin/edit-booking.html.twig', [
            'vgPostnordBookingEditForm' => $bookingForm->createView(),
            'ajaxurl' => $this->get('router')->generate('admin_vg_postnord_ajax_service_point_action'),
            'layoutTitle' => $this->trans('Edit Booking', 'Modules.Vgpostnord.Admin'),
            'layoutHeaderToolbarBtn' => $this->getToolbarButtons($idOrder),
        ]);
    }
    /**
     * @AdminSecurity("is_granted(['create'], request.get('_legacy_controller'))", message="Access denied.")
     *
     * @param Request $request
     *
     * @return Response
     * 
     */
    public function ajaxServicePointAction(Request $request): Response
    {
        $client = new PostnordClient(
            Configuration::get('VG_POSTNORD_HOST'),
            Configuration::get('VG_POSTNORD_APIKEY')
        );
        $carrierSetting = json_decode(Configuration::get('VG_POSTNORD_CARRIER_SETTINGS'), true);
        $idOrder = (int) $request->request->get('idOrder');
        $postalCode = $request->request->get('zipcode');
        $order = new Order($idOrder);
        $idCarrier = (int) $order->id_carrier;
        $idAddress = (int) $order->id_address_delivery;

        $address = new Address($idAddress);
        $countryIsoCode = Country::getIsoById($address->id_country);

        $params = [
            'countryCode' => $countryIsoCode,
            'agreementCountry' => $countryIsoCode,
            //'city' => $address->city,
            'postalCode' => $postalCode,
            //'streetName' => $address->address1,
            //'streetNumber' => '19',
            'numberOfServicePoints' => 100, // TODO: this should probably be a setting?
            'typeId' => $carrierSetting[$idCarrier]['service_codes'] // "type of the service point" or service code, see module configuration page
        ];

        try {
            $response = $client->getServicePointsByAddress($params);
            
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
    private function _getFileName(VgPostnordBooking $booking): string
    {
        return  "label_" . $booking->getIdOrder() . "_" . $booking->getId() . ".pdf";
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
        return $this->json(
            base64_decode($booking->getLabelData()),
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
     * @return array
     */
    private function getToolbarButtons($id_order)
    {
        $toolbarButtons = [];
        $toolbarButtons['go_to_order'] = [
            'href' => $this->generateUrl('admin_orders_view', ["orderId" => $id_order]),
            'desc' => $this->trans('Go to Order', "Modules.Vgpostnord.Admin"),
            'icon' => 'arrow_back',
            // 'help' => $this->trans('Create a new product: CTRL+P', 'Admin.Catalog.Help'),
        ];
        return $toolbarButtons;
    }
}
