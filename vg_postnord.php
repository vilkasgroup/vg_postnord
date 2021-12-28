<?php

use Vilkas\Postnord\Client\PostnordClient;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Vg_postnord extends CarrierModule
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'vg_postnord';
        $this->tab = 'shipping_logistics';
        $this->version = '0.0.1';
        $this->author = 'Vilkas Group Oy';
        $this->need_instance = 0;

        /*
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans("Postnord", [], "Modules.Vgpostnord.Admin");
        $this->description = $this->trans("Postnord shipping for your Prestashop", [], "Modules.Vgpostnord.Admin");

        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];
    }

    public function isUsingNewTranslationSystem(): bool
    {
        return true;
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install(): bool
    {
        Configuration::updateValue('VG_POSTNORD_DEBUG_MODE', false);
        Configuration::updateValue('VG_POSTNORD_HOST', '');
        Configuration::updateValue('VG_POSTNORD_APIKEY', '');
        Configuration::updateValue('VG_POSTNORD_ISSUER_COUNTRY', '');
        Configuration::updateValue('VG_POSTNORD_CARRIER_SETTINGS', '[]');

        return parent::install()
            && $this->installSQL()
            && $this->registerHook('header')
            && $this->registerHook('backOfficeHeader')
            && $this->registerHook('displayCarrierExtraContent')
            ;
    }

    public function uninstall(): bool
    {
        Configuration::deleteByName('VG_POSTNORD_DEBUG_MODE');
        Configuration::deleteByName('VG_POSTNORD_HOST');
        Configuration::deleteByName('VG_POSTNORD_APIKEY');
        Configuration::deleteByName('VG_POSTNORD_ISSUER_COUNTRY');
        Configuration::deleteByName('VG_POSTNORD_CARRIER_SETTINGS');

        return parent::uninstall()
            && $this->uninstallSQL()
            ;
    }

    /**
     * Create SQL Tables for module.
     *
     * @return bool `true` if every entity gets created correctly
     */
    private function installSQL(): bool
    {
        $queries = include dirname(__FILE__).'/sql/install.php';
        if (is_array($queries)) {
            return $this->performInstallQueries($queries);
        } else {
            return false;
        }
    }

    /**
     * Drops module SQL tables.
     *
     * @return bool `true` if removed correctly
     */
    private function uninstallSQL(): bool
    {
        $queries = include dirname(__FILE__).'/sql/uninstall.php';
        if (is_array($queries)) {
            return $this->performInstallQueries($queries);
        } else {
            return false;
        }
    }

    /**
     * Execute a collection of SQL queries of the installation/uninstallation procedures.
     *
     * @param array $queries list of raw SQL queries to execute
     *
     * @return bool `true` if all queries were executed successfully
     */
    private function performInstallQueries(array $queries): bool
    {
        foreach ($queries as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Load the configuration form
     */
    public function getContent(): string
    {
        /*
         * If values have been submitted in the form, process.
         */
        $message = '';
        if ((Tools::isSubmit('submitVg_postnordModule')) == true) {
            if ($this->postProcess()) {
                $message = $this->displayConfirmation(
                    $this->trans("Settings saved successfully.", [], "Modules.Vgpostnord.Admin")
                );
            } else {
                $message = $this->displayError(
                    $this->trans("Could not save settings.", [], "Modules.Vgpostnord.Admin")
                );
            }
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');

        return $message . $output . $this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm(): string
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitVg_postnordModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'fields_value' => $this->getAllFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm($this->getConfigForms());
    }

    protected function getConfigForms(): array
    {
        return [
            'general' => $this->getConfigForm(),
            'carriers' => $this->getCarrierConfigForm(),
        ];
    }

    protected function getAllFormValues(): array
    {
        return array_merge($this->getConfigFormValues(), $this->getCarrierConfigFormValues());
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm(): array
    {
        return [
            'form' => [
                'legend' => [
                'title' => $this->trans("Settings", [], "Modules.Vgpostnord.Admin"),
                'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'name' => 'VG_POSTNORD_DEBUG_MODE',
                        'label' => $this->trans("Debug mode", [], "Modules.Vgpostnord.Admin"),
                        'desc' => $this->trans("Write more debug logs", [], "Modules.Vgpostnord.Admin"),
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->trans("Enabled", [], "Modules.Vgpostnord.Admin"),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->trans("Disabled", [], "Modules.Vgpostnord.Admin"),
                            ],
                        ],
                    ],
                    [
                        'type' => 'select',
                        'name' => 'VG_POSTNORD_ISSUER_COUNTRY',
                        'label' => $this->trans("Postnord issuer Country", [], "Modules.Vgpostnord.Admin"),
                        'options' => [
                            'query' => [
                                ['id' => 'FI', 'name' => 'FI'],
                                ['id' => 'AX', 'name' => 'AX'],
                                ['id' => 'SE', 'name' => 'SE'],
                                ['id' => 'DK', 'name' => 'DK'],
                                ['id' => 'NO', 'name' => 'NO'],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                            'default' => null,
                        ],
                        'desc' => $this->trans("Get this information from Postnord.", [], "Modules.Vgpostnord.Admin"),
                    ],
                    [
                        'type' => 'text',
                        'name' => 'VG_POSTNORD_HOST',
                        'label' => $this->trans("Postnord hostname", [], "Modules.Vgpostnord.Admin"),
                        'desc' => $this->trans("Get this information from Postnord. Usually something like: atapi2.postnord.com", [], "Modules.Vgpostnord.Admin"),
                    ],
                    [
                        'type' => 'text',
                        'name' => 'VG_POSTNORD_APIKEY',
                        'label' => $this->trans("Postnord apikey", [], "Modules.Vgpostnord.Admin"),
                        'desc' => $this->trans("Get this information from Postnord. Something like abc123123123123abc123", [], "Modules.Vgpostnord.Admin"),
                    ],
                ],
                'submit' => [
                    'title' => $this->trans("Save", [], "Modules.Vgpostnord.Admin"),
                ],
            ],
        ];
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues(): array
    {
        return [
            'VG_POSTNORD_DEBUG_MODE' => Configuration::get('VG_POSTNORD_DEBUG_MODE'),
            'VG_POSTNORD_HOST' => Configuration::get('VG_POSTNORD_HOST'),
            'VG_POSTNORD_APIKEY' => Configuration::get('VG_POSTNORD_APIKEY'),
            'VG_POSTNORD_ISSUER_COUNTRY' => Configuration::get('VG_POSTNORD_ISSUER_COUNTRY'),
        ];
    }

    /**
     * Creates a form for mapping carriers to pakettikauppa delivery methods.
     *
     * If api connection fails shows a warning message instead of the form
     *
     * These carrier configs are saved in VG_POSTNORD_CARRIER_SETTINGS as json
     *
     * And postprocess and getValues handles converting the values
     */
    protected function getCarrierConfigForm(): array
    {
        $carriers = Carrier::getCarriers((int) $this->context->language->id, true, false, false, null, Carrier::ALL_CARRIERS);

        $form = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Carrier Delivery Method Settings', [], 'Modules.Vgpostnord.Admin'),
                    'icon' => 'icon-cogs',
                ],
                'submit' => [
                    'title' => $this->trans("Save", [], "Modules.Vgpostnord.Admin"),
                ],
            ],
        ];

        $host = Configuration::get('VG_POSTNORD_HOST');
        $apikey = Configuration::get('VG_POSTNORD_APIKEY');
        $issuerCountry = Configuration::get('VG_POSTNORD_ISSUER_COUNTRY');

        // if settings are not yet complete, show message instead of the form
        if (!$host || !$apikey) {
            $form['form']['warning'] = $this->trans('Please complete Host and Apikey settings to configure Carriers.', [], 'Modules.Vgpostnord.Admin');
            return $form;
        }

        // get listing of possible Postnord service codes and extra services that are available for it
        try {
            // first selection is empty
            $ServiceCodes[] = [
                'serviceCode_consigneeCountry' => 0,
                'serviceName' => ' --- ',
            ];

            // get the possible service codes
            $client = new PostnordClient($host, $apikey);
            $BasicServiceCodes = $client->getBasicServiceCodesFilterByIssuerCountryCode($issuerCountry);

            // sort by id and name and consignee country to have some resemblance of login in the list
            array_multisort(
                array_column($BasicServiceCodes, 'serviceCode'),
                array_column($BasicServiceCodes, 'serviceName'),
                array_column($BasicServiceCodes, 'allowedConsigneeCountry'),
                SORT_ASC,
                $BasicServiceCodes
            );

            // and add them to the dropdown list
            foreach ($BasicServiceCodes as $BasicServiceCode) {

                $name = sprintf('%s, %s (%s => %s)', $BasicServiceCode['serviceCode'], $BasicServiceCode['serviceName'], $BasicServiceCode['allowedConsigneeCountry'], $BasicServiceCode['allowedConsignorCountry']);

                $ServiceCodes[] = [
                    'serviceCode_consigneeCountry' => $BasicServiceCode['serviceCode'] . '_' . $BasicServiceCode['allowedConsigneeCountry'],
                    'serviceName' => $name,
                ];
            }
        } catch (Exception $e) {
            $form['form']['error'] = $this->trans('Failed fetching data from Postnord, check Host and Apikey', [], 'Modules.Vgpostnord.Admin');
            $form['form']['description'] = $e->getMessage();
            return $form;
        }

        // build setting fields for each carrier
        // each carrier is prefixed with id_carrier_reference
        // and then their reference id
        // and then the setting
        foreach ($carriers as $carrier) {
            // just a label
            $carrier_selections[] = [
                'type' => 'free',
                'name' => 'id_carrier_reference_'.$carrier['id_reference'],
                'label' => '<b>' . $carrier['name'] .'</b>',
            ];

            // which service code to use
            $carrier_selections[] = [
                'type' => 'select',
                'options' => [
                    'query' => $ServiceCodes,
                    'id' => 'serviceCode_consigneeCountry',
                    'name' => 'serviceName',
                    'default' => null,
                ],
                'name' => 'id_carrier_reference_'.$carrier['id_reference']. '_service_code_consigneecountry',
                'label' => $this->trans('Service code', [] , 'Modules.Vgpostnord.Admin'),
                'class' => 'fixed-width-xxl',
            ];

            // which service codes to fetch pickup locations for
            $carrier_selections[] = [
                'type' => 'text',
                'name' => 'id_carrier_reference_'.$carrier['id_reference']. '_service_codes',
                'label' => $this->trans('Service codes for pickup', [] , 'Modules.Vgpostnord.Admin'),
            ];

        }

        $form['form']['input'] = $carrier_selections;

        return $form;
    }

    /**
     * parse VG_POSTNORD_CARRIER_SETTINGS to config form values
     */
    protected function getCarrierConfigFormValues(): array
    {
        $carriers = Carrier::getCarriers((int) $this->context->language->id, true, false, false, null, Carrier::ALL_CARRIERS);
        $carrierValues = [];

        $carrierSettings = json_decode(Configuration::get('VG_POSTNORD_CARRIER_SETTINGS'), true);

        foreach ($carriers as $carrier) {
            // just for the label (free text). always empty data
            $carrierValues['id_carrier_reference_'.$carrier['id_reference']] = '';

            $keys = ['service_code_consigneecountry', 'service_codes'];
            foreach ($keys as $key) {
                $carrierValues['id_carrier_reference_'.$carrier['id_reference'].'_'.$key] = $carrierSettings[$carrier['id_reference']][$key];
            }
        }

        return $carrierValues;
    }

    /**
     * Save form data.
     */
    protected function postProcess(): bool
    {
        $result = true;

        // basic config form values
        $config_form_values = $this->getConfigFormValues();
        foreach (array_keys($config_form_values) as $key) {
            $result &= Configuration::updateValue($key, Tools::getValue($key));
        }

        // carrier settings into one json
        $carrier_form_values = $this->getCarrierConfigFormValues();
        $carrier_config = [];
        foreach (array_keys($carrier_form_values) as $key) {
            // format is id_carrier_reference_IDX_key (except for the label which does not have a key at all)
            $newkey = str_replace('id_carrier_reference_', '', $key);
            $idx = filter_var($newkey, FILTER_SANITIZE_NUMBER_INT);

            // skip the label
            if ($newkey == $idx) {
                continue;
            }
            $newkey = str_replace($idx.'_', '', $newkey);

            $carrier_config[$idx][$newkey] = Tools::getValue($key);

        }

        // set the carriers that are marked to use pickuip to is_module so that it can do displayCarrierExtraContent
        foreach ($carrier_config as $id_carrier_reference => $oneconfig) {
            if(strlen($oneconfig['service_codes'])) {
                $this->setCarrierToPostNord($id_carrier_reference, true);
            } else {
                $this->setCarrierToPostNord($id_carrier_reference, false);
            }
        }

        // and save the carrier config
        $result &= Configuration::updateValue('VG_POSTNORD_CARRIER_SETTINGS', json_encode($carrier_config));

        return $result;
    }

    /**
     * Set carrier to `is_module` = 1 to get displayCarrierExtraContent to trigger
     */
    public function setCarrierToPostNord(int $id_carrier_reference, bool $status): bool {
        $db = \Db::getInstance();
        if($status) {
            return $db->Execute(
                'UPDATE `'._DB_PREFIX_.'carrier`
                SET
                `external_module_name` = "vg_postnord",
                `is_module` = 1,
                `need_range` = 1
                WHERE `id_reference` = '.(int) $id_carrier_reference
            );
        } else {
            return $db->Execute(
                'UPDATE `'._DB_PREFIX_.'carrier`
                SET `external_module_name` = "",
                `is_module` = 0,
                `need_range` = 0
                WHERE `id_reference` = '.(int) $id_carrier_reference
                .' AND external_module_name="vg_postnord"'
            );
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be loaded in the BO.
     */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path . 'views/js/back.js');
            $this->context->controller->addCSS($this->_path . 'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path . '/views/js/front.js');
        $this->context->controller->addCSS($this->_path . '/views/css/front.css');
    }

    /**
     * FRONT OFFICE / Carrier selection
     *
     * If the selected carrier is marked as a postnord carrier that has pickup locations show a selection screen of
     * pickup point to the customer
     *
     * The pickup point will be saved as a ajax request to be used for label creation later
     */
    public function hookDisplayCarrierExtraContent()
    {
        return $this->display(__FILE__, 'carrierextracontent.tpl');
    }



    /**
     * required as we are the carrier module
     *
     * as we are setting the need_range=1 for the carrier the
     * getOrderShippingCost method will be called
     */
    public function getOrderShippingCost($params, $shipping_cost)
    {
        // just pass back the original shipping_cost
        return $shipping_cost;
    }
    public function getOrderShippingCostExternal($params)
    {
        return false;
    }
}
