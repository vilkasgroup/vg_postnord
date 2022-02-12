<?php

declare(strict_types=1);

namespace Vilkas\Postnord\Form;

use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Translation\TranslatorInterface;

use PrestaShop\PrestaShop\Adapter\Entity\Configuration;
use PrestaShop\PrestaShop\Adapter\Entity\Db;
use PrestaShop\PrestaShop\Adapter\Entity\DbQuery;
use PrestaShopBundle\Form\Admin\Type\Material\MaterialChoiceTableType;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Vilkas\Postnord\Client\PostnordClient;

class VgPostnordBookingType extends TranslatorAwareType
{
    private $dbQuery;

    /** @var string[] */
    private $mandatory_service_codes = [];

    /**
     * @param TranslatorInterface $translator
     * @param array $locales
     */
    public function __construct(TranslatorInterface $translator, array $locales)
    {
        $this->dbQuery = new DbQuery();
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

        $builder
            // Not used yet
            // ->add('tracking_url', TextType::class, [
            //     'label' => $this->trans('Tracking URL', 'Modules.Vgpostnord.Admin'),
            //     'disabled' => empty($options['data']['tracking_url'])
            // ])
            ->add('additional_services', MaterialChoiceTableType::class, [
                'label' => $this->trans('Additional Services', 'Modules.Vgpostnord.Admin'),
                'help' => $this->trans('Enable additional services for the shipment', 'Modules.Vgpostnord.Admin'),
                'choices' => $this->getAdditionalServices($options),
                'choice_attr' => function($choice) {
                    $disabled = false;
                    // disable editing of mandatory service codes
                    if (in_array($choice, $this->mandatory_service_codes)) {
                        $disabled = true;
                    }
                    return $disabled === true ? ['disabled' => true] : [];
                }
            ]);

        // HACK: add mandatory services codes as hidden inputs, so they get POSTed
        $builder->add("mandatory_service_codes", CollectionType::class, [
            'data' => $this->mandatory_service_codes,
            'label' => false,
            'entry_type' => HiddenType::class,
            'entry_options' => [
                'attr' => ['readonly' => 'true']
            ]
        ]);
    }

    private function getAdditionalServices(&$options)
    {
        $host = Configuration::get('VG_POSTNORD_HOST');
        $apikey = Configuration::get('VG_POSTNORD_APIKEY');
        $issuerCountry = Configuration::get('VG_POSTNORD_ISSUER_COUNTRY');
        $carrierSetting = json_decode(Configuration::get('VG_POSTNORD_CARRIER_SETTINGS'), true);
        $client = new PostnordClient($host, $apikey);
        $idOrder = (int) $options['data']['id_order'];

        // get correct carrier setting for this order to extract from VG_POSTNORD_CARRIER_SETTINGS
        $this->dbQuery->select('id_carrier')
            ->from('orders', 'o')
            ->where("o.id_order = {$idOrder}");
        $idCarrier = (int) (Db::getInstance()->executeS($this->dbQuery))[0]['id_carrier'];

        // split carrierSetting into ['servicecode', 'consigneeCountry']
        $carrierSetting = explode('_', $carrierSetting[$idCarrier]["service_code_consigneecountry"]);

        // Get valid combination from postnord and filter with issuer country, service code and consignee country
        // additional services with mandatory tag are not shown
        $validCombination = ($client->getValidCombinationsOfServiceCodes())['data'];
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
}
