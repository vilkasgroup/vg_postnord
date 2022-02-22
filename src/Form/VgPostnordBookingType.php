<?php

declare(strict_types=1);

namespace Vilkas\Postnord\Form;

use Exception;
use PrestaShop\PrestaShop\Adapter\Entity\Address;
use PrestaShop\PrestaShop\Adapter\Entity\Configuration;
use PrestaShop\PrestaShop\Adapter\Entity\Country;
use PrestaShop\PrestaShop\Adapter\Entity\Order;
use PrestaShopBundle\Form\Admin\Type\Material\MaterialChoiceTableType;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;

use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Translation\TranslatorInterface;

use Vilkas\Postnord\Client\PostnordClient;

class VgPostnordBookingType extends TranslatorAwareType
{
    private $client;

    /** @var string[] */
    private $mandatory_service_codes = [];

    /**
     * @param TranslatorInterface $translator
     * @param array $locales
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

        $builder
            // Not used yet
            // ->add('tracking_url', TextType::class, [
            //     'label' => $this->trans('Tracking URL', 'Modules.Vgpostnord.Admin'),
            //     'disabled' => empty($options['data']['tracking_url'])
            // ])
            ->add('id_order', HiddenType::class)
            ->add('additional_services', MaterialChoiceTableType::class, [
                'label' => $this->trans('Additional Services', 'Modules.Vgpostnord.Admin'),
                'help' => $this->trans('Enable additional services for the shipment', 'Modules.Vgpostnord.Admin'),
                'choices' => $this->getAdditionalServices($options),
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
            ]);

        // Only show service point selector when carrier support service point(A7)
        if (in_array('A7', $this->mandatory_service_codes)) {
            $builder
                ->add('current_service_point', TextType::class, [
                    'label' => $this->trans('Current Service Point', 'Modules.Vgpostnord.Admin'),
                    'disabled' => true,
                ])
                ->add($builder->create('change_service_point', FormType::class, [
                    'label' => false,
                    'row_attr' => ['class' => 'changeButton']
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
                    $form->add('servicepointid', MaterialChoiceTableType::class, [
                        'label' => $this->trans('New Service Point', 'Modules.Vgpostnord.Admin'),
                        'help' => $this->trans('Change to New Service Point', 'Modules.Vgpostnord.Admin'),
                        'choices' => [$data['servicepointid'] => $data['servicepointid']],
                        'multiple' => false,
                        'row_attr' => ['class' => 'servicePointIdPicker d-none']
                    ]);

                    $event->setData($data);
                })
                ->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
                    // get form, options from event
                    $form = $event->getForm();
                    $data = $event->getData();
                    // get submitted form data

                    $servicePoints = !empty($data['servicepointid']) ? $data['servicepointid'] : null;
                    $currentServicePoint = $data['servicepointid_value'];
                    $idOrder = $data['id_order'];

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
                            $servicePointData = $this->getServicePointData($idOrder, $servicePoints);
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
    }

    private function getAdditionalServices($options): array
    {
        $issuerCountry = Configuration::get('VG_POSTNORD_ISSUER_COUNTRY');
        $carrierSetting = json_decode(Configuration::get('VG_POSTNORD_CARRIER_SETTINGS'), true);
        $idOrder = (int) $options['data']['id_order'];
        $order = new Order($idOrder);
        $idCarrier = (int) $order->id_carrier;
        // split carrierSetting into ['servicecode', 'consigneeCountry']
        $carrierSetting = explode('_', $carrierSetting[$idCarrier]["service_code_consigneecountry"]);

        // Get valid combination from postnord and filter with issuer country, service code and consignee country
        // additional services with mandatory tag are not shown
        $validCombination = ($this->client->getValidCombinationsOfServiceCodes())['data'];
        $validIssuerCountryCombination = array_filter($validCombination, function ($element) use (&$issuerCountry) {
            return $element['issuerCountryCode'] === $issuerCountry ? $element : null;
        });
        $finalCombination = array_reduce(
            reset($validIssuerCountryCombination)['adnlServiceCodeCombDetails'],
            function ($carry, $element) use (&$carrierSetting) {
                if (
                    $element['serviceCode'] === $carrierSetting[0]
                    && $element['allowedConsigneeCountry'] === $carrierSetting[1]
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
    // Only work for PS 1.7.8 
    // PS 1.7.7 MaterialChoiceTableType does not support radio for some reason 
    private function getServicePoint($options): array
    {
        $carrierSetting = json_decode(Configuration::get('VG_POSTNORD_CARRIER_SETTINGS'), true);
        $idOrder = (int) $options['data']['id_order'];
        $order = new Order($idOrder);
        $idCarrier = (int) $order->id_carrier;
        $idAddress = (int) $order->id_address_delivery;
        $address = new Address($idAddress);
        $postalCode = $address->postcode;
        $countryIsoCode = Country::getIsoById($address->id_country);

        $params = [
            'countryCode' => $countryIsoCode,
            'agreementCountry' => $countryIsoCode,
            //'city' => $address->city,
            'postalCode' => $postalCode,
            //'streetName' => $address->address1,
            //'streetNumber' => '19',
            'numberOfServicePoints' => 100,
            'typeId' => $carrierSetting[$idCarrier]['service_codes'] // "type of the service point" or service code, see module configuration page
        ];
        try {
            $response = $this->client->getServicePointsByAddress($params);
            if (!empty($response['servicePoints'])) {
                $servicePoints = $this->getServicePointOption($response['servicePoints']);
                return [$servicePoints, $postalCode];
            } else {
                return [[$response['error'] => null], $postalCode];
            }
        } catch (Exception $e) {
            return [
                [$e->getMessage() => null],
                $postalCode
            ];
        }
    }
    private function getServicePointData($idOrder, $servicePointId): array
    {
        $order = new Order($idOrder);
        $idAddress = (int) $order->id_address_delivery;
        $address = new Address($idAddress);
        $countryIsoCode = Country::getIsoById($address->id_country);

        $params = [
            'countryCode' => $countryIsoCode,
            'ids' => $servicePointId
        ];

        return $this->client->getServicePointById($params);
    }

    private function getServicePointOption($servicePoints): array
    {
        return array_reduce($servicePoints, function ($carry, $element) {
            $carry["{$element['name']}. 
            {$element['visitingAddress']['streetName']}
            {$element['visitingAddress']['streetNumber']},
            {$element['visitingAddress']['postalCode']}
            {$element['visitingAddress']['city']}
            "] = $element['servicePointId'];
            return $carry;
        }, []);
    }
    private function getPostalCode($options): string
    {
        $idOrder = (int) $options['data']['id_order'];
        $order = new Order($idOrder);
        $idAddress = (int) $order->id_address_delivery;
        $address = new Address($idAddress);
        $postalCode = $address->postcode;
        return $postalCode;
    }
}
