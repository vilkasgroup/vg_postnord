<?php

declare(strict_types=1);

namespace Vilkas\Postnord\Form;

use Address;
use Carrier;
use Configuration;
use Country;
use Exception;
use Order;
use PrestaShopException;
use PrestaShopBundle\Form\Admin\Type\Material\MaterialChoiceTableType;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Vilkas\Postnord\Client\PostnordClient;

class VgPostnordBookingType extends TranslatorAwareType
{
    private $client;

    /** @var string[] */
    private $mandatory_service_codes = [];

    /**
     * @param TranslatorInterface $translator
     * @param array $locales
     *
     * @throws Exception
     */
    public function __construct(TranslatorInterface $translator, array $locales)
    {
        $this->client = new PostnordClient(
            Configuration::get('VG_POSTNORD_HOST'),
            Configuration::get('VG_POSTNORD_APIKEY')
        );

        parent::__construct($translator, $locales);
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // To disable editing when finalized.
        // I couldn't find a way to disable the edit button
        // So let settle with disabled form with this instead.
        $builder->setDisabled(!empty($options['data']['finalized']));

        $postalCode = $this->getPostalCode($options);
        $additionalServicesChoices = $this->getAdditionalServices($options);

        $builder
            ->add('id_order', HiddenType::class)
            ->add('additional_services', MaterialChoiceTableType::class, [
                'label' => $this->trans('Additional Services', 'Modules.Vgpostnord.Admin'),
                'help' => $this->trans('Enable additional services for the shipment', 'Modules.Vgpostnord.Admin'),
                'choices' => $additionalServicesChoices,
                'choice_attr' => function ($choice) {
                    $disabled = false;
                    // disable editing of mandatory service codes
                    if (in_array($choice, $this->mandatory_service_codes)) {
                        $disabled = true;
                    }
                    return $disabled === true ? ['disabled' => true] : [];
                }
            ])
            // HACK: add mandatory services codes as hidden inputs, so they get POSTed
            ->add('mandatory_service_codes', CollectionType::class, [
                'data' => $this->mandatory_service_codes,
                'label' => false,
                'entry_type' => HiddenType::class
            ])
            ->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($additionalServicesChoices) {
                $form = $event->getForm();

                if (!empty($additionalServicesChoices['error'])) {
                    $form->addError(new FormError("Error: {$additionalServicesChoices['error']}"));
                    $form->addError(new FormError("{$additionalServicesChoices['errorMessage']}"));
                    $form->remove('additional_services');
                }
            });

        // Only show service point selector when carrier support service point(A7)
        if (in_array('A7', $this->mandatory_service_codes)) {
            $builder
                ->add('current_service_point', TextType::class, [
                    'label' => $this->trans('Current Service Point', 'Modules.Vgpostnord.Admin'),
                    'disabled' => true,
                ])
                ->add($builder->create('change_service_point', FormType::class, [
                    'label' => false,
                    'row_attr' => ['class' => 'vg-postnord-booking-change-service-point-button']
                ])
                    ->add('button', ButtonType::class, [
                        'attr' => ['class' => 'search btn-primary float-right col px-md-5'],
                        'label' => $this->trans('Change Service Point', 'Modules.Vgpostnord.Admin'),
                    ]))
                // use this one to get servicepointid
                ->add('servicepointid', HiddenType::class)
                ->add('service_point_data', HiddenType::class)
                ->add('postcode', TextType::class, [
                    'label' => $this->trans('Postal Code', 'Modules.Vgpostnord.Admin'),
                    'required'   => false,
                    'data' => $postalCode,
                    'row_attr' => ['class' => 'd-none']
                ])
                ->add($builder->create('button', FormType::class, [
                    'label' => false,
                    'attr' => ['class' => 'd-none']
                ])
                    ->add('search', ButtonType::class, [
                        'attr' => ['class' => 'search btn-primary float-right col px-md-5'],
                        'label' => $this->trans('Search', 'Modules.Vgpostnord.Admin'),
                    ]))
                ->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
                    // get form, options from event
                    $form = $event->getForm();
                    $data = $event->getData();

                    // get service_point_data
                    if (!empty($data['service_point_data'])) {
                        $servicePointData = json_decode($data['service_point_data'], true);
                        // Show the current selected service point
                        $data['current_service_point'] = "{$servicePointData['name']}. {$servicePointData['visitingAddress']['streetName']} {$servicePointData['visitingAddress']['streetNumber']}, {$servicePointData['visitingAddress']['postalCode']} {$servicePointData['visitingAddress']['city']}";
                    }

                    // used to compare, then conditional fetching service_point_data
                    $form->add('servicepointid_value', HiddenType::class, [
                        'data' => $data['servicepointid'],
                    ]);
                    // add servicepointid table. The default choice is the
                    // current servicepointid to prevent bug when submit
                    // without changing.
                    $form->add('servicepointid', VgPostnordMaterialChoiceTableType::class, [
                        'label' => $this->trans('New Service Point', 'Modules.Vgpostnord.Admin'),
                        'help' => $this->trans('Change to New Service Point', 'Modules.Vgpostnord.Admin'),
                        'choices' => [$data['servicepointid'] => $data['servicepointid']],
                        'multiple' => false,
                        'row_attr' => ['class' => 'vg-postnord-service-point-id-picker d-none']
                    ]);

                    $event->setData($data);
                })
                ->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
                    // get form, options from event
                    $form = $event->getForm();
                    $data = $event->getData();
                    // get submitted form data

                    $servicePoints = !empty($data['servicepointid']) ? $data['servicepointid'] : null;
                    // TODO: if service point fetching fails due to API error (like 502), this might prevent the form form being saved ('servicepointid_value' is not found in data)
                    // TODO: I actually somehow ended up with empty service point id, data and additional services, simulate an api error and test this out
                    //       (they probably shouldn't be changed if the widget can't be loaded)
                    $currentServicePoint = $data['servicepointid_value'];
                    $id_order = $data['id_order'];

                    // create new choices list
                    $choices = [];

                    // data only return array if it's multiple choice/checkbox
                    // So this one will always return string but keep it here
                    // as a reference.

                    if (is_array($servicePoints)) {
                        foreach ($servicePoints as $choice) {
                            $choices[$choice] = $choice;
                        }
                    } else {
                        $choices[$servicePoints] = $servicePoints;
                        // only update service_point_data if changed
                        if ($servicePoints !== $currentServicePoint) {
                            $servicePointData = $this->getServicePointData($id_order, $servicePoints);
                            if (empty($servicePointData['error'])) {
                                $data['service_point_data'] = json_encode($servicePointData);
                                $event->setData($data);
                                // Add field with new choices to form
                                $form->add('servicepointid', ChoiceType::class, [
                                    'choices' => $choices
                                ]);
                            } else {
                                $form->addError(new FormError("Error: {$servicePointData['error']}"));
                            }
                        }
                    }
                });
        }

        $builder
            ->add('add_parcel_button', ButtonType::class, [
                'attr' => ['class' => 'vg-postnord-add-parcel btn btn-primary'],
                'label' => $this->trans('Add parcel', 'Modules.Vgpostnord.Admin')
            ])
            ->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
                $data = $event->getData();
                $form = $event->getForm();

                // decode JSON-based fields
                $decode = ['parcel_data', 'detailed_description', 'customs_declaration_data'];
                foreach ($decode as $key) {
                    $data[$key] = !empty($data[$key]) ? json_decode($data[$key], true) : null;
                }
                $event->setData($data);

                $parcel_count          = count($data['parcel_data']);
                $content_line_count    = !empty($data['detailed_description']) ? count($data['detailed_description']) : 0;
                $default_tariff_number = Configuration::get('VG_POSTNORD_DEFAULT_TARIFF_NUMBER');

                $customs_declaration = [
                    'customs_declaration_data' => $data['customs_declaration_data'],
                    'detailed_description'     => $data['detailed_description']
                ];

                $form
                    ->add('parcel_data', CollectionType::class, [
                        'data' => $data['parcel_data'],
                        'label' => $this->trans('Parcel data', 'Modules.Vgpostnord.Admin'),
                        'entry_type' => VgPostnordParcelType::class,
                        // new fields are generated/deleted by javascript, so these seem to be needed
                        'allow_extra_fields' => true,
                        'allow_add' => true,
                        'allow_delete' => true
                    ])
                    ->add('parcel_count', HiddenType::class, [
                        'data' => $parcel_count
                    ])
                    ->add('customs_declaration_checkbox', CheckboxType::class, [
                        'data' => $data['customs_declaration'],
                        'attr' => ['class' => 'vg-postnord-customs-declaration-checkbox'],
                        'label' => $this->trans('Customs declaration', 'Modules.Vgpostnord.Admin'),
                        'required' => false
                    ])
                    ->add('customs_declaration', VgPostnordCustomsDeclarationType::class, [
                        'data' => $customs_declaration,
                        'label' => false
                    ])
                    ->add('content_line_count', HiddenType::class, [
                        'data' => $content_line_count
                    ])
                    ->add('default_tariff_number', HiddenType::class, [
                        'data' => $default_tariff_number
                    ])
                ;
            })
        ;
    }

    private function getAdditionalServices($options): array
    {
        $issuerCountry = Configuration::get('VG_POSTNORD_ISSUER_COUNTRY');
        $carrierSetting = json_decode(Configuration::get('VG_POSTNORD_CARRIER_SETTINGS'), true);

        $id_order = (int) $options['data']['id_order'];
        $order = new Order($id_order);
        $carrier = new Carrier($order->id_carrier);

        [$service_code, $consignee_country] = explode('_', $carrierSetting[$carrier->id_reference]["service_code_consigneecountry"]);

        // Get valid combination from postnord and filter with issuer country, service code and consignee country
        // additional services with mandatory tag are not shown
        try {
            $validCombination = ($this->client->getValidCombinationsOfServiceCodes())['data'];
        } catch (Exception | ExceptionInterface $e) {
            return ['error' => 'Failed to fetch additional services', 'errorMessage' => $e->getMessage()];
        }
        $validIssuerCountryCombination = array_filter($validCombination, function ($element) use ($issuerCountry) {
            return $element['issuerCountryCode'] === $issuerCountry ? $element : null;
        });
        $finalCombination = array_reduce(
            reset($validIssuerCountryCombination)['adnlServiceCodeCombDetails'],
            function ($carry, $element) use ($service_code, $consignee_country) {
                if (
                    $element['serviceCode'] === $service_code
                    && $element['allowedConsigneeCountry'] === $consignee_country
                ) {
                    $carry[] = [$element['adnlServiceName'] => $element['adnlServiceCode']];
                    if ($element['mandatory'] === true) {
                        $this->mandatory_service_codes[] = $element['adnlServiceCode'];
                    }
                }
                return $carry;
            },
            []
        );

        // Just to make it look nicer, I guess
        usort($finalCombination, function ($a, $b) {
            if ($a == $b) {
                return 0;
            }
            return (reset($a) > reset($b)) ? 1 : -1;
        });
        return $finalCombination;
    }

    /**
     * @throws PrestaShopException
     * @throws ExceptionInterface
     */
    private function getServicePointData($id_order, $servicePointId): array
    {
        $order = new Order($id_order);
        $id_address = (int) $order->id_address_delivery;
        $address = new Address($id_address);
        $countryCode = Country::getIsoById($address->id_country);

        $params = [
            'countryCode' => $countryCode,
            'ids' => $servicePointId
        ];

        return $this->client->getServicePointById($params);
    }

    private function getPostalCode($options): string
    {
        $id_order = (int) $options['data']['id_order'];
        $order = new Order($id_order);
        $id_address = (int) $order->id_address_delivery;
        $address = new Address($id_address);

        return $address->postcode;
    }
}
