<?php

declare(strict_types=1);

namespace Vilkas\Postnord\Form;

use Exception;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Translation\TranslatorInterface;

use PrestaShop\PrestaShop\Adapter\Entity\Address;
use PrestaShop\PrestaShop\Adapter\Entity\Configuration;
use PrestaShop\PrestaShop\Adapter\Entity\Country;
use PrestaShop\PrestaShop\Adapter\Entity\Db;
use PrestaShop\PrestaShop\Adapter\Entity\DbQuery;
use PrestaShop\PrestaShop\Adapter\Entity\Order;
use PrestaShopBundle\Form\Admin\Type\Material\MaterialChoiceTableType;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
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
        list($servicePoints, $postalCode) = $this->getServicePoint($options);

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
                'entry_type' => HiddenType::class,
                'entry_options' => [
                    'attr' => ['readonly' => 'true']
                ]
            ])
            ->add('servicepointid', MaterialChoiceTableType::class, [
                'label' => $this->trans('Service Point', 'Modules.Vgpostnord.Admin'),
                'help' => $this->trans('Service Point', 'Modules.Vgpostnord.Admin'),
                'choices' => $servicePoints,
                'multiple' => false,
                'row_attr' => ['class' => 'servicePointIdPicker']
            ])
            ->add('service_point_data', HiddenType::class, [
                'data' => ' '
            ])
            ->add('postcode', TextType::class, [
                'label' => $this->trans('Postal Code', 'Modules.Vgpostnord.Admin'),
                'required'   => false,
                'data' => $postalCode,
            ])
            ->add($builder->create('button', FormType::class, [
                'label' => false
            ])
                ->add('search', ButtonType::class, [
                    'attr' => ['class' => 'search btn-primary float-right col px-md-5'],
                    'label' => $this->trans('Search', 'Modules.Vgpostnord.Admin'),
                ]))

            ->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
                // get form, options from event
                $form = $event->getForm();
                $data = $event->getData();
                // get submitted form data
                $servicePoints = $data['servicepointid'];
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
                    $servicePointData = $this->getServicePointData($idOrder, $servicePoints);
                    $data['service_point_data'] = json_encode($servicePointData);
                    $event->setData($data);
                }

                // Add field with new choices to form
                $form->add('servicepointid', ChoiceType::class, [
                    'choices' => $choices
                ]);
            });
    }

    private function getAdditionalServices($options): array
    {
        $issuerCountry = Configuration::get('VG_POSTNORD_ISSUER_COUNTRY');
        $carrierSetting = json_decode(Configuration::get('VG_POSTNORD_CARRIER_SETTINGS'), true);
        $idOrder = (int) $options['data']['id_order'];
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
            'numberOfServicePoints' => 100, // TODO: this should probably be a setting?
            'typeId' => $carrierSetting[$idCarrier]['service_codes'] // "type of the service point" or service code, see module configuration page
        ];
        try {
            $response = $this->client->getServicePointsByAddress($params);
            if (!empty($response['servicePoints'])) {
                $servicePoints = array_reduce($response['servicePoints'], function ($carry, $element) {
                    $carry["{$element['name']}. 
                    {$element['visitingAddress']['streetName']}
                    {$element['visitingAddress']['streetNumber']},
                    {$element['visitingAddress']['postalCode']}
                    {$element['visitingAddress']['city']}
                    "] = $element['servicePointId'];
                    return $carry;
                }, []);
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
}
