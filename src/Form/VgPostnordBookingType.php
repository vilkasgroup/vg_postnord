<?php

declare(strict_types=1);

namespace Vilkas\Postnord\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Translation\TranslatorInterface;

use PrestaShop\PrestaShop\Adapter\Entity\Configuration;
use PrestaShop\PrestaShop\Adapter\Entity\Db;
use PrestaShop\PrestaShop\Adapter\Entity\DbQuery;
use PrestaShopBundle\Form\Admin\Type\CommonAbstractType;
use PrestaShopBundle\Form\Admin\Type\Material\MaterialChoiceTableType;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;

use Vilkas\Postnord\Client\PostnordClient;

class VgPostnordBookingType extends CommonAbstractType
{
    private $dbQuery;
    /**
     * @param TranslatorInterface $translator
     * @param array $locales
     */
    public function __construct()
    {
        $this->dbQuery = new DbQuery();
        //     parent::__construct($translator, $locales);TranslatorInterface $translator, array $locales
    }
    // $this->trans('Tracking URL', 'Modules.Vgpostnord.Admin')
    // $this->trans('Additional Services', 'Modules.Vgpostnord.Admin')
    // $this->trans('Enable additional services for the shipment', 'Modules.Vgpostnord.Admin')
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {

        $additionalServices = $this->getAdditionalServices($options);

        $builder
            // ->add('tracking_url', TextType::class, [
            //     'label' => 'Tracking URL',
            // ])
            ->add('additional_services', MaterialChoiceTableType::class, [
                'label' => 'Additional Services',
                'help' => 'Additional Services',
                'choices' => $additionalServices,
            ]);
    }

    private function getAdditionalServices(&$options)
    {
        $host = Configuration::get('VG_POSTNORD_HOST');
        $apikey = Configuration::get('VG_POSTNORD_APIKEY');
        $issuerCountry = Configuration::get('VG_POSTNORD_ISSUER_COUNTRY');
        $carrierSetting = json_decode(Configuration::get('VG_POSTNORD_CARRIER_SETTINGS'), true);
        $client = new PostnordClient($host, $apikey);
        $idOrder = $options['data']['id_order'];

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
                    && !$element['mandatory']
                ) {
                    array_push($carry, [
                        $element["adnlServiceName"] => $element["adnlServiceCode"]
                    ]);
                }
                return $carry;
            },
            []
        );

        // Just to make it look nicer, I guess
        usort($finalCombination, function($a, $b)
        {
            if ($a == $b) {
                return 0;
            }
            return (reset($a) > reset($b)) ? 1 : -1;
        });
        return $finalCombination;
    }
}
