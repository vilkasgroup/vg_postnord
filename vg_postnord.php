<?php

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use PrestaShop\PrestaShop\Core\Grid\Definition\GridDefinition;
use PrestaShopBundle\Controller\Admin\Sell\Order\ActionsBarButton;
use PrestaShopBundle\Controller\Admin\Sell\Order\ActionsBarButtonsCollection;
use Psr\Log\AbstractLogger;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Vilkas\Postnord\Client\PostnordClient;
use Vilkas\Postnord\Entity\VgPostnordBooking;
use Vilkas\Postnord\Entity\VgPostnordCartData;
use Vilkas\Postnord\Grid\Action\VgPostnordJavascriptAction;
use Vilkas\Postnord\Validator\VgPostnordPartyIdValidator;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Vg_postnord extends CarrierModule
{
    protected $config_form = false;

    /** @var AbstractLogger */
    private $logger;

    public function __construct()
    {
        $this->name = 'vg_postnord';
        $this->tab = 'shipping_logistics';
        $this->version = '1.1.2';
        $this->author = 'Vilkas Group Oy';
        $this->need_instance = 0;

        /*
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Postnord', [], 'Modules.Vgpostnord.Admin');
        $this->description = $this->trans('Postnord shipping for your Prestashop', [], 'Modules.Vgpostnord.Admin');

        $this->ps_versions_compliancy = ['min' => '1.7.7', 'max' => _PS_VERSION_];

        $this->tabs = [
            [
                'name' => $this->trans('Postnord Shipments', [], 'Modules.Vgpostnord.Admin'),
                'parent_class_name' => 'AdminParentOrders',
                'route_name' => 'admin_vg_postnord_list_action',
                'class_name' => 'VgPostnordBookingController',
                'visible' => true,
            ]
        ];

        $this->logger = static::getLogger();
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
        Configuration::updateValue('VG_POSTNORD_FETCH_BOTH', false);
        Configuration::updateValue('VG_POSTNORD_DIFFERENT_RETURN_ADDRESS', false);
        Configuration::updateValue('VG_POSTNORD_HOST', '');
        Configuration::updateValue('VG_POSTNORD_APIKEY', '');
        Configuration::updateValue('VG_POSTNORD_ISSUER_COUNTRY', '');
        Configuration::updateValue('VG_POSTNORD_PARTY_ID', '');
        Configuration::updateValue('VG_POSTNORD_EORI_NUMBER', '');
        Configuration::updateValue('VG_POSTNORD_DEFAULT_TARIFF_NUMBER', '');
        Configuration::updateValue('VG_POSTNORD_LABEL_PAPER_SIZE', 'A5');
        Configuration::updateValue('VG_POSTNORD_CARRIER_SETTINGS', '[]');
        Configuration::updateValue('VG_POSTNORD_SHOP_ADDRESS', '[]');
        Configuration::updateValue('VG_POSTNORD_RETURN_ADDRESS', '[]');

        return parent::install()
            && $this->installSQL()
            && $this->registerHook('displayHeader')
            && $this->registerHook('actionAdminControllerSetMedia')
            && $this->registerHook('displayCarrierExtraContent')
            && $this->registerHook('displayAdminOrderMain')
            && $this->registerHook('actionValidateOrder')
            && $this->registerHook('displayAdminEndContent')

            // show possible selected pickup location in SF my account old order view
            && $this->registerHook('displayOrderDetail')
            // show possible selected pickup location in order confirmation page
            && $this->registerHook('displayOrderConfirmation1')

            // add "fetch label" button to order preview
            && $this->registerHook('displayOrderPreview')
            // add "fetch label" button to order buttons
            && $this->registerHook('actionGetAdminOrderButtons')
            // add "fetch label" button to order bulk actions
            && $this->registerHook('actionOrderGridDefinitionModifier')

            // add service point information to order confirmation template variables
            && $this->registerHook('actionGetExtraMailTemplateVars')

            // update booking when changing order carrier
            && $this->registerHook('actionObjectOrderUpdateBefore')
            ;
    }

    public function uninstall(): bool
    {
        Configuration::deleteByName('VG_POSTNORD_DEBUG_MODE');
        Configuration::deleteByName('VG_POSTNORD_FETCH_BOTH');
        Configuration::deleteByName('VG_POSTNORD_DIFFERENT_RETURN_ADDRESS');
        Configuration::deleteByName('VG_POSTNORD_HOST');
        Configuration::deleteByName('VG_POSTNORD_APIKEY');
        Configuration::deleteByName('VG_POSTNORD_ISSUER_COUNTRY');
        Configuration::deleteByName('VG_POSTNORD_PARTY_ID');
        Configuration::deleteByName('VG_POSTNORD_EORI_NUMBER');
        Configuration::deleteByName('VG_POSTNORD_DEFAULT_TARIFF_NUMBER');
        Configuration::deleteByName('VG_POSTNORD_LABEL_PAPER_SIZE');
        Configuration::deleteByName('VG_POSTNORD_CARRIER_SETTINGS');
        Configuration::deleteByName('VG_POSTNORD_SHOP_ADDRESS');
        Configuration::deleteByName('VG_POSTNORD_RETURN_ADDRESS');

        return parent::uninstall()
            && $this->uninstallSQL();
    }

    /**
     * Create SQL Tables for module.
     *
     * @return bool `true` if every entity gets created correctly
     */
    private function installSQL(): bool
    {
        $queries = include dirname(__FILE__) . '/sql/install.php';
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
        $queries = include dirname(__FILE__) . '/sql/uninstall.php';
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

    public static function getLogger(): Logger
    {
        $logger = new Logger('vg_postnord');
        $logger->pushHandler(new StreamHandler(_PS_ROOT_DIR_ . '/var/logs/postnord.log'));

        return $logger;
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
                    $this->trans('Settings saved successfully.', [], 'Modules.Vgpostnord.Admin')
                );
            } else {
                $message = $this->displayError(
                    $this->trans('Could not save settings.', [], 'Modules.Vgpostnord.Admin')
                );
            }
        }

        $this->context->smarty->assign('module_dir', $this->_path);
        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');
        $footer = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure_footer.tpl');

        return $message . $output . $this->renderForm() . $footer;
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
        $form = [
            'general' => $this->getConfigForm(),
            'carriers' => $this->getCarrierConfigForm(),
            'address' => $this->getAddressConfigForm(),
        ];
        if (Configuration::get('VG_POSTNORD_DIFFERENT_RETURN_ADDRESS')) {
            $form['return'] = $this->getReturnAddressConfigForm();
        }

        return $form;
    }

    protected function getAllFormValues(): array
    {
        return array_merge(
            $this->getConfigFormValues(),
            $this->getCarrierConfigFormValues(),
            $this->getAddressConfigFormValues(),
            Configuration::get('VG_POSTNORD_DIFFERENT_RETURN_ADDRESS') ? $this->getReturnAddressConfigFormValues() : []
        );
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm(): array
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Settings', [], 'Modules.Vgpostnord.Admin'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'name' => 'VG_POSTNORD_DEBUG_MODE',
                        'label' => $this->trans('Debug mode', [], 'Modules.Vgpostnord.Admin'),
                        'desc' => $this->trans('Write more debug logs', [], 'Modules.Vgpostnord.Admin'),
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->trans('Enabled', [], 'Modules.Vgpostnord.Admin'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->trans('Disabled', [], 'Modules.Vgpostnord.Admin'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'name' => 'VG_POSTNORD_FETCH_BOTH',
                        'label' => $this->trans('Fetch both labels', [], 'Modules.Vgpostnord.Admin'),
                        'desc' => $this->trans('Fetch shipping label and return label at the same time', [], 'Modules.Vgpostnord.Admin'),
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->trans('Enabled', [], 'Modules.Vgpostnord.Admin'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->trans('Disabled', [], 'Modules.Vgpostnord.Admin'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'name' => 'VG_POSTNORD_DIFFERENT_RETURN_ADDRESS',
                        'label' => $this->trans('Different return address', [], 'Modules.Vgpostnord.Admin'),
                        'desc' => $this->trans('Enable if the return address is different than shop address', [], 'Modules.Vgpostnord.Admin'),
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->trans('Enabled', [], 'Modules.Vgpostnord.Admin'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->trans('Disabled', [], 'Modules.Vgpostnord.Admin'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'select',
                        'name' => 'VG_POSTNORD_ISSUER_COUNTRY',
                        'label' => $this->trans('Postnord issuer country', [], 'Modules.Vgpostnord.Admin'),
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
                        'desc' => $this->trans('Get this information from Postnord.', [], 'Modules.Vgpostnord.Admin'),
                    ],
                    [
                        'type' => 'text',
                        'name' => 'VG_POSTNORD_HOST',
                        'label' => $this->trans('Postnord hostname', [], 'Modules.Vgpostnord.Admin'),
                        'desc' => $this->trans('Get this information from Postnord. Usually something like: atapi2.postnord.com', [], 'Modules.Vgpostnord.Admin'),
                        'required' => true
                    ],
                    [
                        'type' => 'text',
                        'name' => 'VG_POSTNORD_APIKEY',
                        'label' => $this->trans('Postnord apikey', [], 'Modules.Vgpostnord.Admin'),
                        'desc' => $this->trans('Get this information from Postnord. Something like abc123123123123abc123', [], 'Modules.Vgpostnord.Admin'),
                        'required' => true
                    ],
                    [
                        'type' => 'text',
                        'name' => 'VG_POSTNORD_PARTY_ID',
                        'label' => $this->trans('Party ID', [], 'Modules.Vgpostnord.Admin'),
                        'desc' => $this->trans('10 digits long customer number (Party ID). Get this information from Postnord', [], 'Modules.Vgpostnord.Admin'),
                        'required' => true
                    ],
                    [
                        'type' => 'text',
                        'name' => 'VG_POSTNORD_EORI_NUMBER',
                        'label' => $this->trans('EORI number', [], 'Modules.Vgpostnord.Admin'),
                        'desc' => $this->trans('Required for all customs declarations', [], 'Modules.Vgpostnord.Admin'),
                    ],
                    [
                        'type' => 'text',
                        'name' => 'VG_POSTNORD_DEFAULT_TARIFF_NUMBER',
                        'label' => $this->trans('Default tariff number', [], 'Modules.Vgpostnord.Admin'),
                        'desc' => $this->trans('Default HS tariff number (see: tulltaxan.tullverket.se). Used to prefill tariff number for customs declarations.', [], 'Modules.Vgpostnord.Admin'),
                    ],
                    [
                        'type' => 'select',
                        'name' => 'VG_POSTNORD_LABEL_PAPER_SIZE',
                        'label' => $this->trans('Label paper size', [], 'Modules.Vgpostnord.Admin'),
                        'options' => [
                            'query' => [
                                ['id' => 'A4', 'name' => 'A4'],
                                ['id' => 'A5', 'name' => 'A5'],
                                ['id' => 'LABEL', 'name' => 'LABEL'],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                            'default' => null,
                        ],
                        'desc' => $this->trans('Paper size for labels.', [], 'Modules.Vgpostnord.Admin'),
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Modules.Vgpostnord.Admin'),
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
            'VG_POSTNORD_FETCH_BOTH' => Configuration::get('VG_POSTNORD_FETCH_BOTH'),
            'VG_POSTNORD_DIFFERENT_RETURN_ADDRESS' => Configuration::get('VG_POSTNORD_DIFFERENT_RETURN_ADDRESS'),
            'VG_POSTNORD_HOST' => Configuration::get('VG_POSTNORD_HOST'),
            'VG_POSTNORD_APIKEY' => Configuration::get('VG_POSTNORD_APIKEY'),
            'VG_POSTNORD_ISSUER_COUNTRY' => Configuration::get('VG_POSTNORD_ISSUER_COUNTRY'),
            'VG_POSTNORD_PARTY_ID' => Configuration::get('VG_POSTNORD_PARTY_ID'),
            'VG_POSTNORD_EORI_NUMBER' => Configuration::get('VG_POSTNORD_EORI_NUMBER'),
            'VG_POSTNORD_DEFAULT_TARIFF_NUMBER' => Configuration::get('VG_POSTNORD_DEFAULT_TARIFF_NUMBER'),
            'VG_POSTNORD_LABEL_PAPER_SIZE' => Configuration::get('VG_POSTNORD_LABEL_PAPER_SIZE'),
        ];
    }

    /**
     * Create a form to store shop address
     * address will be stored in VG_POSTNORD_SHOP_ADDRESS as json
     */
    protected function getAddressConfigForm(): array
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Shop Address Setting', [], 'Modules.Vgpostnord.Admin'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'name' => 'shop_name',
                        'label' => $this->trans('Shop name', [], 'Modules.Vgpostnord.Admin'),
                        'desc' => $this->trans('Sender name', [], 'Modules.Vgpostnord.Admin'),
                    ],
                    [
                        'type' => 'text',
                        'name' => 'shop_street',
                        'label' => $this->trans('Shop street address', [], 'Modules.Vgpostnord.Admin'),
                        'desc' => $this->trans('Sender street address', [], 'Modules.Vgpostnord.Admin'),
                    ],
                    [
                        'type' => 'text',
                        'name' => 'shop_postcode',
                        'label' => $this->trans('Shop postal code', [], 'Modules.Vgpostnord.Admin'),
                        'desc' => $this->trans('Sender postal code', [], 'Modules.Vgpostnord.Admin'),
                    ],
                    [
                        'type' => 'text',
                        'name' => 'shop_city',
                        'label' => $this->trans('Shop city', [], 'Modules.Vgpostnord.Admin'),
                        'desc' => $this->trans('Sender city', [], 'Modules.Vgpostnord.Admin'),
                    ],
                    [
                        'type' => 'select',
                        'name' => 'shop_country',
                        'label' => $this->trans('Shop country', [], 'Modules.Vgpostnord.Admin'),
                        'options' => [
                            'query' => [
                                ['id' => 'FI', 'name' => 'Finland'],
                                ['id' => 'AX', 'name' => 'Åland'],
                                ['id' => 'SE', 'name' => 'Sweden'],
                                ['id' => 'DK', 'name' => 'Denmark'],
                                ['id' => 'NO', 'name' => 'Norway'],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                            'default' => null,
                        ],
                        'desc' => $this->trans('Sender country', [], 'Modules.Vgpostnord.Admin'),
                    ],
                    [
                        'type' => 'text',
                        'name' => 'shop_phone',
                        'label' => $this->trans('Shop phone', [], 'Modules.Vgpostnord.Admin'),
                        'desc' => $this->trans('Sender phone', [], 'Modules.Vgpostnord.Admin'),
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Modules.Vgpostnord.Admin'),
                ],
            ],
        ];
    }

    /**
     * parse VG_POSTNORD_SHOP_ADDRESS to config form values
     */
    public function getAddressConfigFormValues(): array
    {
        $address = json_decode(Configuration::get('VG_POSTNORD_SHOP_ADDRESS', true), true);

        if (empty($address)) {
            return [
                'shop_name' => '',
                'shop_street' => '',
                'shop_postcode' => '',
                'shop_city' => '',
                'shop_country' => '',
                'shop_phone' => '',
            ];
        }

        return $address;
    }

    /**
     * Create a form to store shop address
     * address will be stored in VG_POSTNORD_RETURN_ADDRESS as json
     */
    protected function getReturnAddressConfigForm(): array
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Return Address Setting', [], 'Modules.Vgpostnord.Admin'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'name' => 'return_name',
                        'label' => $this->trans('Return name', [], 'Modules.Vgpostnord.Admin'),
                        'desc' => $this->trans('Sender name', [], 'Modules.Vgpostnord.Admin'),
                    ],
                    [
                        'type' => 'text',
                        'name' => 'return_street',
                        'label' => $this->trans('Return street address', [], 'Modules.Vgpostnord.Admin'),
                        'desc' => $this->trans('Sender street address', [], 'Modules.Vgpostnord.Admin'),
                    ],
                    [
                        'type' => 'text',
                        'name' => 'return_postcode',
                        'label' => $this->trans('Return postal code', [], 'Modules.Vgpostnord.Admin'),
                        'desc' => $this->trans('Sender postal code', [], 'Modules.Vgpostnord.Admin'),
                    ],
                    [
                        'type' => 'text',
                        'name' => 'return_city',
                        'label' => $this->trans('Return city', [], 'Modules.Vgpostnord.Admin'),
                        'desc' => $this->trans('Sender city', [], 'Modules.Vgpostnord.Admin'),
                    ],
                    [
                        'type' => 'select',
                        'name' => 'return_country',
                        'label' => $this->trans('Return country', [], 'Modules.Vgpostnord.Admin'),
                        'options' => [
                            'query' => [
                                ['id' => 'FI', 'name' => 'Finland'],
                                ['id' => 'AX', 'name' => 'Åland'],
                                ['id' => 'SE', 'name' => 'Sweden'],
                                ['id' => 'DK', 'name' => 'Denmark'],
                                ['id' => 'NO', 'name' => 'Norway'],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                            'default' => null,
                        ],
                        'desc' => $this->trans('Sender country', [], 'Modules.Vgpostnord.Admin'),
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Modules.Vgpostnord.Admin'),
                ],
            ],
        ];
    }

    /**
     * parse VG_POSTNORD_RETURN_ADDRESS to config form values
     */
    public function getReturnAddressConfigFormValues(): array
    {
        $address = json_decode(Configuration::get('VG_POSTNORD_RETURN_ADDRESS', true), true);

        if (empty($address)) {
            return [
                'return_name' => '',
                'return_street' => '',
                'return_postcode' => '',
                'return_city' => '',
                'return_country' => '',
            ];
        }

        return $address;
    }

    /**
     * Creates a form for mapping carriers to Postnord delivery methods.
     *
     * If api connection fails shows a warning message instead of the form
     *
     * These carrier configs are saved in VG_POSTNORD_CARRIER_SETTINGS as json
     *
     * And postprocess and getValues handles converting the values
     */
    protected function getCarrierConfigForm(): array
    {
        $carriers = Carrier::getCarriers((int) $this->context->language->id, false, false, false, null, Carrier::ALL_CARRIERS);

        $form = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Carrier Delivery Method Settings', [], 'Modules.Vgpostnord.Admin'),
                    'icon' => 'icon-cogs',
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Modules.Vgpostnord.Admin'),
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

            $valid_combinations = $client->getValidCombinationsOfServiceCodes()["data"];
            Media::addJsDef([
                'validCombinations' => $valid_combinations
            ]);

            // sort by id and name and consignee country to have some resemblance of logic in the list
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

        $carrier_selections = [];

        // build setting fields for each carrier,
        // each carrier is prefixed with id_carrier_reference
        // and then their reference id
        // and then the setting
        foreach ($carriers as $carrier) {
            // just a label
            $carrier_selections[] = [
                'type' => 'free',
                'name' => 'id_carrier_reference_' . $carrier['id_reference'],
                'label' => '<b>' . $carrier['name'] . '</b>',
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
                'name' => 'id_carrier_reference_' . $carrier['id_reference'] . '_service_code_consigneecountry',
                'label' => $this->trans('Service code', [], 'Modules.Vgpostnord.Admin'),
                'class' => 'fixed-width-xxl',
                'desc' => $this->trans('Service code for this carrier', [], 'Modules.Vgpostnord.Admin'),
            ];

            // which service codes to fetch pickup locations for
            $carrier_selections[] = [
                'type' => 'text',
                'name' => 'id_carrier_reference_' . $carrier['id_reference'] . '_service_codes',
                'label' => $this->trans('Service codes for pickup', [], 'Modules.Vgpostnord.Admin'),
                'desc' => $this->trans('Comma separated list of service codes to use to filter pickuppoints. See possible values below. Leave empty for no filtering.', [], 'Modules.Vgpostnord.Admin'),
            ];

            // additional service as a hidden text which will be filled with checkbox
            $carrier_selections[] = [
                'type' => 'text',
                'name' => 'id_carrier_reference_' . $carrier['id_reference'] . '_additional_service_codes',
                'label' => $this->trans('Additional service codes', [], 'Modules.Vgpostnord.Admin'),
                'desc' => $this->trans('Set default additional service codes for this delivery method.', [], 'Modules.Vgpostnord.Admin'),
                'class' => 'additional_service_codes hidden'
            ];
        }

        if (!$carrier_selections) {
            $form['form']['description'] = $this->trans('No carriers found.', [], 'Modules.Vgpostnord.Admin');
        }

        $form['form']['input'] = $carrier_selections;

        return $form;
    }

    /**
     * parse VG_POSTNORD_CARRIER_SETTINGS to config form values
     */
    public function getCarrierConfigFormValues(): array
    {
        $carriers = Carrier::getCarriers((int) $this->context->language->id, false, false, false, null, Carrier::ALL_CARRIERS);
        $carrierValues = [];

        $carrierSettings = $this->getCarrierConfigurations();

        foreach ($carriers as $carrier) {
            // just for the label (free text). always empty data
            $carrierValues['id_carrier_reference_' . $carrier['id_reference']] = '';

            $keys = ['service_code_consigneecountry', 'service_codes'];
            foreach ($keys as $key) {
                $carrierValues['id_carrier_reference_' . $carrier['id_reference'] . '_' . $key] = $carrierSettings[$carrier['id_reference']][$key] ?? '';
            }

            // special case: convert additional service codes into a JSON string for the hidden text input
            $carrierValues['id_carrier_reference_' . $carrier['id_reference'] . '_additional_service_codes'] = json_encode($carrierSettings[$carrier['id_reference']]['additional_service_codes']) ?? '';
        }

        return $carrierValues;
    }

    /**
     * get All carrier configurations, basically just decoded configuration
     * The array id is carrier id_reference
     */
    public function getCarrierConfigurations(): array
    {
        return json_decode(Configuration::get('VG_POSTNORD_CARRIER_SETTINGS'), true);
    }

    /**
     * get one carrier configuration
     */
    public function getCarrierConfiguration($id_carrier_reference): array
    {
        return $this->getCarrierConfigurations()[$id_carrier_reference];
    }

    /**
     * Get both mandatory and additional service codes for a given carrier config
     *
     * @param array $carrier_config
     *
     * @return array
     */
    public static function getCombinedServiceCodesForConfig(array $carrier_config): array
    {
        $mandatory = $additional = [];

        if (array_key_exists("additional_service_codes", $carrier_config)) {
            $additional = $carrier_config["additional_service_codes"];
        }
        if (array_key_exists("mandatory_service_codes", $carrier_config)) {
            $mandatory = $carrier_config["mandatory_service_codes"];
        }

        return array_unique(array_merge($mandatory, $additional));
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

        if (!VgPostnordPartyIdValidator::partyIdIsValid(Tools::getValue("VG_POSTNORD_PARTY_ID"))) {
            $this->context->controller->errors[] = $this->trans("Party ID is not valid.", [], "Modules.Vgpostnord.Admin");
        }

        // address config into json
        $address_form_values = $this->getAddressConfigFormValues();
        $address_config = [];
        foreach (array_keys($address_form_values) as $key) {
            $address_config[$key] = Tools::getValue($key);
        }

        $result &= Configuration::updateValue('VG_POSTNORD_SHOP_ADDRESS', json_encode($address_config));

        // address config into json
        $return_address_form_values = $this->getReturnAddressConfigFormValues();
        $return_address_config = [];
        foreach (array_keys($return_address_form_values) as $key) {
            $return_address_config[$key] = Tools::getValue($key);
        }

        $result &= Configuration::updateValue('VG_POSTNORD_RETURN_ADDRESS', json_encode($return_address_config));

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
            $newkey = str_replace($idx . '_', '', $newkey);

            $carrier_config[$idx][$newkey] = Tools::getValue($key);
        }

        try {
            $client = new PostnordClient(
                Configuration::get("VG_POSTNORD_HOST"),
                Configuration::get("VG_POSTNORD_APIKEY")
            );
            $valid_combinations = $client->getValidCombinationsOfServiceCodes()["data"];
        } catch (Exception | ExceptionInterface $e) {
            $msg = $this->trans("Error fetching service code combinations: %error%", ["%error%" => $e->getMessage()], "Modules.Vgpostnord.Admin");
            $this->context->controller->errors[] = $msg;
            return false;
        }

        foreach ($carrier_config as $id_carrier_reference => &$oneconfig) {
            // set the carriers that are marked to use pickup to is_module so that it can do displayCarrierExtraContent
            if ($oneconfig['service_code_consigneecountry']) {
                $this->setCarrierToPostNord($id_carrier_reference, true);
            } else {
                $this->setCarrierToPostNord($id_carrier_reference, false);
            }

            // convert additional_service_codes from JSON string to array for storage,
            // so it won't be double-encoded inside the database
            $asc = json_decode($oneconfig["additional_service_codes"]) ?? [];
            $oneconfig["additional_service_codes"] = $asc;

            // TODO: swear there's a better way to do whatever the following lines do

            if (!$oneconfig["service_code_consigneecountry"]) {
                $oneconfig["mandatory_service_codes"] = [];
                continue;
            }

            $split = explode("_", $oneconfig["service_code_consigneecountry"]);
            [$service_code, $consignee_country] = $split;

            // find combinations related to issuer country
            $valid_country_combinations = array_filter($valid_combinations, function ($element) use ($consignee_country) {
                return $element['issuerCountryCode'] === $consignee_country ? $element : null;
            });
            if (empty($valid_country_combinations)) {
                $oneconfig["mandatory_service_codes"] = [];
                continue;
            }

            // find mandatory services for service code and consignee country
            $valid_country_combinations = reset($valid_country_combinations)["adnlServiceCodeCombDetails"];
            $mandatory_combinations = array_filter($valid_country_combinations, function ($element) use ($service_code, $consignee_country) {
                return $element["mandatory"] === true
                    && $element["serviceCode"] === $service_code
                    && $element["allowedConsigneeCountry"] === $consignee_country;
            });
            // grab 'adnlServiceCode' from every matching service
            $mandatory_service_codes = array_column($mandatory_combinations, "adnlServiceCode");
            $oneconfig["mandatory_service_codes"] = $mandatory_service_codes;
        }
        unset($oneconfig);

        // and save the carrier config
        $result &= Configuration::updateValue('VG_POSTNORD_CARRIER_SETTINGS', json_encode($carrier_config));

        return $result;
    }

    /**
     * Set carrier to `is_module` = 1 to get displayCarrierExtraContent to trigger
     */
    public function setCarrierToPostNord(int $id_carrier_reference, bool $status): bool
    {
        $db = \Db::getInstance();
        if ($status) {
            return $db->Execute(
                'UPDATE `' . _DB_PREFIX_ . 'carrier`
                SET
                `external_module_name` = "vg_postnord",
                `is_module` = 1,
                `need_range` = 1
                WHERE `id_reference` = ' . (int) $id_carrier_reference
            );
        } else {
            return $db->Execute(
                'UPDATE `' . _DB_PREFIX_ . 'carrier`
                SET `external_module_name` = "",
                `is_module` = 0,
                `need_range` = 0
                WHERE `id_reference` = ' . (int) $id_carrier_reference
                    . ' AND external_module_name="vg_postnord"'
            );
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be loaded in the BO.
     */
    public function hookActionAdminControllerSetMedia()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path . 'views/js/back.js');
            $this->context->controller->addCSS($this->_path . 'views/css/back.css');
        }
        if (Tools::getValue('controller') == 'AdminOrders') {
            $this->context->controller->addCSS($this->_path . 'views/css/back.css');
        }
        if (Tools::getValue('configure') === $this->name) {
            $this->context->controller->addJS($this->_path . '/views/js/config.js');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookDisplayHeader()
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
     * The pickup point will be saved as an ajax request to be used for label creation later
     */
    public function hookDisplayCarrierExtraContent($params)
    {
        // don't show pickup point selection if the "optional service point" additional service hasn't been
        // selected and isn't mandatory
        $carrier_config = $this->getCarrierConfiguration((int) $params["carrier"]["id_reference"]);
        $service_codes = static::getCombinedServiceCodesForConfig($carrier_config);
        if (!in_array("A7", $service_codes)) {
            return null;
        }

        // prefill with the zipcode user has already given
        $id_address = $params['cart']->id_address_delivery;
        $address = new Address($id_address);
        $this->context->smarty->assign('vg_postnord_carrier_id', intval($params['carrier']['id']));
        $this->context->smarty->assign('vg_postnord_carrier_reference', intval($params['carrier']['id_reference']));
        $this->context->smarty->assign('vg_postnord_postcode_prefill', $address->postcode);

        $ctrl_url = $this->context->link->getModuleLink('vg_postnord', 'CartPickupPoint', [], true);
        $this->context->smarty->assign('vg_postnord_search_pickuppoints_action', $ctrl_url);

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

    public function getOrderShippingCostExternal($params): bool
    {
        return false;
    }

    /**
     * Show shipment actions on the BO order page
     */
    public function hookDisplayAdminOrderMain(array $params): ?string
    {
        $id_order = (int) $params['id_order'];
        try {
            $order = new Order($id_order);
        } catch (PrestaShopException $e) {
            $this->logger->error('Error loading Product', [
                'exception' => $e->getMessage(),
                'hook' => 'displayAdminOrderMain',
                'id_order' => $id_order,
            ]);

            return null;
        }

        $carrier = new Carrier($order->id_carrier);
        if ($carrier->external_module_name !== $this->name) {
            return null; // probably not a PostNord order
        }

        try {
            /** @var EntityManager $entityManager */
            $entityManager = $this->get('doctrine.orm.entity_manager');
            $cartDataRepository = $entityManager->getRepository(VgPostnordCartData::class);
            $bookingRepository  = $entityManager->getRepository(VgPostnordBooking::class);
        } catch (Exception $e) {
            $this->logger->error('Error getting entity manager or repository', [
                'exception' => $e,
                'hook' => 'displayAdminOrderMain',
                'id_order' => $id_order
            ]);

            return null;
        }

        $cartData = $cartDataRepository->findOneBy(['id_order' => $id_order]);
        $bookings = $bookingRepository->findBy(['id_order' => $id_order], ['id' => 'DESC']);

        // get service point data from the latest booking or cart data
        $service_point_data = null;
        if (count($bookings)) {
            $service_point_data = json_decode($bookings[0]->getServicePointData(), true);
        } else {
            if ($cartData) {
                $service_point_data = json_decode($cartData->getServicePointData(), true);
            }
        }

        try {
            /** @var Twig\Environment $twig */
            $twig = $this->get('twig');

            return $twig->render('@Modules/vg_postnord/views/templates/admin/order-actions.html.twig', [
                'id_order'      => $id_order,
                'bookings'      => $bookings,
                'service_point' => $service_point_data
            ]);
        } catch (Exception $e) {
            $this->logger->error('Could not render Twig template', [
                'exception' => $e->getMessage(),
                'hook' => 'displayAdminOrderMain',
                'id_order' => $id_order,
            ]);

            return null;
        }
    }

    /**
     * Add "Fetch label" button to order preview
     */
    public function hookDisplayOrderPreview(array $params): ?string
    {
        $id_order = (int) $params['order_id'];
        if (!$this->isPostNordOrder($id_order)) {
            return null;
        }

        try {
            /** @var Twig\Environment $twig */
            $twig = $this->get('twig');

            /** @var Router $router */
            $router = $this->get('router');
            $route = $router->generate('admin_vg_postnord_create_booking', [
                'id_order' => $id_order,
                'generate_label' => true
            ]);

            return $twig->render('@Modules/vg_postnord/views/templates/admin/order-preview.html.twig', [
                'generate_label_url' => $route
            ]);
        } catch (Exception $e) {
            $this->logger->error('Could not render Twig template', [
                'exception' => $e->getMessage(),
                'hook' => 'displayOrderPreview',
                'id_order' => $id_order,
            ]);

            return null;
        }
    }

    /**
     * Add "Fetch label" button to order page buttons
     */
    public function hookActionGetAdminOrderButtons(array $params)
    {
        $id_order = (int) $params['id_order'];
        if (!$this->isPostNordOrder($id_order)) {
            return null;
        }

        /** @var ActionsBarButtonsCollection $collection */
        $collection = $params['actions_bar_buttons_collection'];

        try {
            /** @var Router $router */
            $router = $this->get('router');
            $route = $router->generate('admin_vg_postnord_create_booking', [
                'id_order' => $id_order,
                'generate_label' => true
            ]);

            $collection->add(
                new ActionsBarButton(
                    'btn-primary btn-generate-label',
                    [
                        'name' => 'vg-postnord-generate-label-button',
                        'onclick' => "window.open('$route', '_blank')"
                    ],
                    $this->trans('Create booking and fetch label', [], 'Modules.Vgpostnord.Admin')
                )
            );
        } catch (Exception $e) {
            $this->logger->error('Error adding ActionsBarButton', [
                'exception' => $e->getMessage(),
                'hook' => 'actionGetAdminOrderButtons',
                'id_order' => $id_order,
            ]);

            return null;
        }
    }

    /**
     * Add "Fetch label" bulk action button to Order grid
     */
    public function hookActionOrderGridDefinitionModifier(array $params)
    {
        /** @var GridDefinition $gridDefinition */
        $gridDefinition = $params['definition'];
        $gridDefinition->getBulkActions()->add(
            (new VgPostnordJavascriptAction('bulk_fetch_label'))
                ->setName($this->trans('Fetch label (PostNord)', [], 'Modules.Vgpostnord.Admin'))
                ->setOptions([
                    'function' => 'vgpostnordBulkFetchLabelAction(this, event);',
                    'modal_id' => 'vgpostnordFetchLabelModal',
                    'route'    => 'admin_vg_postnord_ajax_fetch_label_action'
                ])
        );
    }

    /**
     * Operations when an order is created:
     * - Save id_order in cart data if shipping method is PostNord
     * - Clear service point from cart data if it's not applicable
     * - Pre-select the 'closest' service point if the customer didn't select any (and is applicable)
     * - Fetch and save service point data to cart data if it has a service point id
     */
    public function hookActionValidateOrder(array $params)
    {
        /** @var Cart $cart */
        $cart = $params['cart'];

        /** @var Order $order */
        $order = $params['order'];

        $carrier = new Carrier($order->id_carrier);
        if ($carrier->external_module_name !== $this->name) {
            return; // probably not a PostNord order
        }

        try {
            /** @var EntityManager $entityManager */
            $entityManager = $this->get('doctrine.orm.entity_manager');
            $repository = $entityManager->getRepository(VgPostnordCartData::class);
        } catch (Exception $e) {
            $this->logger->error('Error getting entity manager or repository', [
                'exception' => $e->getMessage(),
                'hook'      => 'actionValidateOrder',
                'id_cart'   => $cart->id,
                'id_order'  => $order->id,
            ]);

            return;
        }

        $cartData = $repository->findOneBy(['id_cart' => $cart->id]);

        try {
            $client = new PostnordClient(
                Configuration::get("VG_POSTNORD_HOST"),
                Configuration::get("VG_POSTNORD_APIKEY")
            );
        } catch (Exception $e) {
            $this->logger->error('Error initializing client', [
                'hook'      => 'actionValidateOrder',
                'exception' => $e,
                'id_order'  => $order->id,
                'id_cart'   => $cart->id
            ]);

            return;
        }

        $carrier_config = $this->getCarrierConfiguration($carrier->id_reference);
        $service_codes = static::getCombinedServiceCodesForConfig($carrier_config);
        if (!in_array("A7", $service_codes)) {
            // have to make this check here and not right after fetching the cart data, since the else clause below
            // will have to create the data if it doesn't exist
            if (!$cartData) {
                return; // no cart data and it's not needed, can return
            }
            // clear service point from cart data if "optional service point" isn't mandatory
            // reason: service point id might be saved to cart data even if selected carrier doesn't support them,
            //         since it is saved as soon as the service point is clicked, even if the user ends up choosing
            //         another carrier later
            $cartData->setServicePointId(null);
        } else {
            // make sure we have a service point if "optional service point" is mandatory
            // reason: one-page checkout modules like Klarna might create the order without selecting a service
            //         point, so we will assign one to ease order handling
            if (!$cartData || !$cartData->getServicePointId()) {
                $this->logger->info("Cart data doesn't exist or it doesn't have a service point, upserting it", [
                    "hook"     => "actionValidateOrder",
                    "id_order" => $order->id
                ]);

                $address     = new Address($order->id_address_delivery);
                $countryCode = Country::getIsoById($address->id_country);
                $typeId      = $carrier_config["service_codes"];

                $params = [
                    'countryCode'           => $countryCode,
                    'agreementCountry'      => $countryCode,
                    'city'                  => $address->city,
                    'postalCode'            => $address->postcode,
                    'streetName'            => $address->address1,
                    'numberOfServicePoints' => 1,
                    'typeId'                => $typeId
                ];

                try {
                    $response = $client->getServicePointsByAddress($params, $service_codes);
                    if (
                        array_key_exists("servicePoints", $response)
                        && !empty($response["servicePoints"])
                        && array_key_exists("servicePointId", $response["servicePoints"][0])
                    ) {
                        $servicePointId = $response["servicePoints"][0]["servicePointId"];
                        $cartData = $repository->upsertCartServicePointId($cart->id, $servicePointId);
                    } else {
                        $this->logger->error("Service point not found in response!", [
                            "hook"     => "actionValidateOrder",
                            "id_order" => $order->id,
                            "id_cart"  => $cart->id
                        ]);
                        return;
                    }
                } catch (ExceptionInterface | Exception $e) {
                    $this->logger->error('Error assigning service point', [
                        'hook'      => 'actionValidateOrder',
                        'exception' => $e,
                        'id_order'  => $order->id,
                        'id_cart'   => $cart->id
                    ]);

                    return;
                }
            }
        }

        // fetch and save service point data to cart data if cart data has a service point id
        if ($cartData->getServicePointId()) {
            try {
                $address = new Address($order->id_address_delivery);
                $country = new Country($address->id_country);

                $params = [
                    "countryCode" => $country->iso_code,
                    "ids" => $cartData->getServicePointId()
                ];
                $service_point = $client->getServicePointById($params);
                $cartData->setServicePointData(json_encode($service_point));
            } catch (Throwable $e) {
                $this->logger->error('Error getting service point data', [
                    'hook'      => 'actionValidateOrder',
                    'exception' => $e,
                    'id_order'  => $order->id,
                    'id_cart'   => $cart->id
                ]);
            }
        }

        $cartData->setIdOrder($order->id);
        try {
            $entityManager->persist($cartData);
            $entityManager->flush();
        } catch (ORMException $e) {
            $this->logger->error('Error updating cart data', [
                'exception' => $e->getMessage(),
                'hook'      => 'actionValidateOrder',
                'id_cart'   => $cart->id,
                'id_order'  => $order->id,
            ]);
        }
    }

    /**
     * Whether a given order is (likely) a PostNord order
     *
     * @param int $id_order Order ID
     *
     * @return bool
     */
    private function isPostNordOrder(int $id_order): bool
    {
        try {
            $order = new Order($id_order);
        } catch (PrestaShopException $e) {
            $this->logger->error('Error loading Product', [
                'exception' => $e->getMessage(),
                'id_order' => $id_order,
            ]);

            return false;
        }

        $carrier = new Carrier($order->id_carrier);
        return $carrier->external_module_name === $this->name;
    }

    /**
     * Add extra template variables to some email templates
     */
    public function hookActionGetExtraMailTemplateVars(array $params)
    {
        if ($params["template"] === "order_conf") {
            $this->setExtraMailTemplateVarsOrderConf($params);
        }

        if ($params["template"] === "shipped") {
            $this->setExtraMailTemplateVarsShipped($params);
        }
    }

    /**
     * Set the followup url in the shipped email
     *
     * @noinspection PhpArrayWriteIsNotUsedInspection
     */
    public function setExtraMailTemplateVarsShipped(array $params)
    {
        if (
            !array_key_exists('template_vars', $params)
            || !array_key_exists('{id_order}', $params['template_vars'])
        ) {
            return;
        }
        $id_order = (int) $params["template_vars"]["{id_order}"];
        if (!$id_order) {
            return;
        }
        if (!$this->isPostNordOrder($id_order)) {
            return;
        }

        // find the latest booking with tracking code
        try {
            /** @var EntityManager $entityManager */
            $entityManager = $this->get('doctrine.orm.entity_manager');
            $bookingRepository  = $entityManager->getRepository(VgPostnordBooking::class);
        } catch (Exception $e) {
            $this->logger->error('Error getting entity manager or repository', [
                'exception' => $e,
                'hook'      => 'displayAdminOrderMain',
                'id_order'  => $id_order
            ]);

            return;
        }

        $bookings = $bookingRepository->findBy(['id_order' => $id_order], ['id' => 'DESC']);
        if (!count($bookings)) {
            return;
        }
        $lastBooking = $bookings[array_key_last($bookings)];

        // booking can have multiple codes, take the last one (arbitrary decision)
        $trackingData = $lastBooking->getTrackingData();
        if (!count($trackingData)) {
            return;
        }
        $lastTracking = $trackingData[array_key_last($trackingData)];
        if (!array_key_exists('url', $lastTracking)) {
            return;
        }

        // and set it to followup for the email
        $url = $lastTracking['url'];
        $params["extra_template_vars"]["{followup}"] = $url;
    }

    /**
     * Add new {postnord_service_point} template variable that contains
     * pickup location information to be used in order_conf email
     *
     * NOTE: you must add the tag into the email in the theme template
     *
     * @noinspection PhpArrayWriteIsNotUsedInspection
     */
    public function setExtraMailTemplateVarsOrderConf(array $params)
    {
        /**
         * Default values (so that nothing is shown if carrier is not PostNord for example)
         */
        $params["extra_template_vars"]["{postnord_service_point}"] = "";
        $params["extra_template_vars"]["{postnord_service_point_no_html}"] = "";

        $id_order = (int) $params["template_vars"]["{id_order}"];
        if (!$id_order) {
            return;
        }
        if (!$this->isPostNordOrder($id_order)) {
            return;
        }

        try {
            /** @var EntityManager $entityManager */
            $entityManager = $this->get("doctrine.orm.entity_manager");
            $repository = $entityManager->getRepository(VgPostnordCartData::class);
        } catch (Exception $e) {
            $this->logger->error("Error getting entity manager or repository", [
                "exception" => $e,
                "hook"      => "actionGetExtraMailTemplateVars",
                "id_order"  => $id_order
            ]);

            return;
        }

        $cartData = $repository->findOneBy(["id_order" => $id_order]);
        if (!$cartData) {
            return;
        }
        $service_point_data = json_decode($cartData->getServicePointData(), true);
        if (!$service_point_data) {
            return;
        }

        $service_point_header = $this->trans("Pickup point", [], "Modules.Vgpostnord.Admin");
        $delivery_address     = $service_point_data["deliveryAddress"];

        $service_point_no_html =
            "{$service_point_header}\r\n" .
            "{$service_point_data["name"]}\r\n" .
            "{$delivery_address["streetName"]} {$delivery_address["streetNumber"]}\r\n" .
            "{$delivery_address["postalCode"]} {$delivery_address["city"]}";

        // Note: seems you can't get an instance of the Symfony container here, so you can't load services like Twig
        try {
            $this->context->smarty->assign([
                "service_point"        => $service_point_data,
                "service_point_header" => $service_point_header
            ]);
            $tpl = $this->context->smarty->fetch($this->local_path . "views/templates/mails/order-confirmation-service-point.tpl");
            $params["extra_template_vars"]["{postnord_service_point}"] = $tpl;
        } catch (Exception $e) {
            $this->logger->error("Couldn't fetch Smarty template", [
                "exception" => $e->getMessage(),
                "hook"      => "actionGetExtraMailTemplateVars",
                "id_order"  => $id_order,
            ]);
        }

        $params["extra_template_vars"]["{postnord_service_point_no_html}"] = $service_point_no_html;
    }

    /**
     * Update additional service, service point when changing carrier
     *
     * @throws Exception
     */
    public function hookActionObjectOrderUpdateBefore(array $params): void
    {
        $order = $params['object'];

        $update_order_shipping = Tools::getValue('update_order_shipping');
        if (!$update_order_shipping) {
            return;
        }

        // don't do anything if carrier hasn't changed
        $orderCurrent = new Order($order->id);
        if ($orderCurrent->id_carrier === $order->id_carrier) {
            return;
        }

        $new_carrier_id = (int) $update_order_shipping['new_carrier_id'];

        try {
            /** @var EntityManager $entityManager */
            $entityManager = $this->get("doctrine.orm.entity_manager");
            $repository = $entityManager->getRepository(VgPostnordBooking::class);
        } catch (Exception $e) {
            $this->logger->error("Error getting entity manager or repository", [
                "exception" => $e,
                "hook"      => "actionObjectOrderUpdateBefore",
                "id_order"  => $order->id
            ]);

            return;
        }

        $booking = $repository->findOneBy(['id_order' => (int) $order->id], ['id' => 'DESC']);

        if (!$booking || $booking->isFinalized()) {
            return;
        }

        $carrier = new Carrier($new_carrier_id);
        $id_reference = $carrier->id_reference;

        // Get mandatory service codes
        $carrierSetting = json_decode(Configuration::get('VG_POSTNORD_CARRIER_SETTINGS'), true);
        $mandatory = $carrierSetting[$id_reference]['mandatory_service_codes'] ?? [];

        // Remove service point info if service point is not mandatory
        if (!in_array('A7', $mandatory)) {
            $booking->setServicepointid(null);
            $booking->setServicePointData(null);
        }

        // Register new mandatory
        $booking->setAdditionalServices(implode(", ", $mandatory));
        try {
            $entityManager->flush();
        } catch (Exception $e) {
            $this->logger->error('Failed to update booking', ['exception' => $e]);
            throw $e;
        }
    }

    /**
     * Show pickup location in storefront order detail views if one is selected
     *
     * @throws Exception
     */
    public function hookDisplayOrderDetail(array $params): string
    {
        /** @var Order $Order */
        $Order = $params['order'];
        if (!$Order) {
            return "";
        }
        if (!$this->isPostNordOrder($Order->id)) {
            return "";
        }

        try {
            /** @var EntityManager $entityManager */
            $entityManager = $this->get("doctrine.orm.entity_manager");
            $repository = $entityManager->getRepository(VgPostnordCartData::class);
        } catch (Exception $e) {
            $this->logger->error("Error getting entity manager or repository", [
                "exception" => $e,
                "hook"      => "hookDisplayOrderDetail",
                "id_order"  => $Order->id
            ]);
            return "";
        }

        $cartData = $repository->findOneBy(["id_order" => $Order->id]);
        if (!$cartData) {
            return "";
        }
        $service_point_data = json_decode($cartData->getServicePointData(), true);
        if (!$service_point_data) {
            return "";
        }

        try {
            $this->context->smarty->assign([
                "service_point" => $service_point_data,
                "service_point_header" => $this->trans("Pickup point", [], "Modules.Vgpostnord.Admin")
            ]);
            return $this->context->smarty->fetch($this->local_path . "views/templates/hook/displayOrderDetail-service-point.tpl");
        } catch (Exception $e) {
            $this->logger->error("Couldn't fetch Smarty template", [
                "exception" => $e->getMessage(),
                "hook"      => "hookDisplayOrderDetail",
                "id_order"  => $Order->id,
            ]);
        }

        return "";
    }

    /**
     * Show pickup location in order confirmation page if one is selected
     *
     * @throws Exception
     */
    public function hookDisplayOrderConfirmation1(): string
    {
        // params does not contain anything sane, read the id_order from url
        $id_order = Tools::getValue('id_order', 0);
        if (!$id_order) {
            return "";
        }

        // check the key
        try {
            $url_secure_key = Tools::getValue('key', 0);
            $customer_secure_key = $this->context->customer->secure_key;
            if ($url_secure_key != $customer_secure_key) {
                return "";
            }
        } catch (Exception $e) {
            $this->logger->error("Failed checking secure key!", [
                "exception" => $e,
                "hook"      => "hookDisplayOrderConfirmation1",
                "id_order"  => $id_order
            ]);
            return "";
        }

        $Order = new Order((int) $id_order);
        if (!$this->isPostNordOrder($Order->id)) {
            return "";
        }

        try {
            /** @var EntityManager $entityManager */
            $entityManager = $this->get("doctrine.orm.entity_manager");
            $repository = $entityManager->getRepository(VgPostnordCartData::class);
        } catch (Exception $e) {
            $this->logger->error("Error getting entity manager or repository", [
                "exception" => $e,
                "hook"      => "hookDisplayOrderConfirmation1",
                "id_order"  => $Order->id
            ]);
            return "";
        }

        $cartData = $repository->findOneBy(["id_order" => $Order->id]);
        if (!$cartData) {
            return "";
        }
        $service_point_data = json_decode($cartData->getServicePointData(), true);
        if (!$service_point_data) {
            return "";
        }

        try {
            $this->context->smarty->assign([
                "service_point" => $service_point_data,
                "service_point_header" => $this->trans("Pickup point", [], "Modules.Vgpostnord.Admin")
            ]);
            return $this->context->smarty->fetch($this->local_path . "views/templates/hook/displayOrderConfirmation1-service-point.tpl");
        } catch (Exception $e) {
            $this->logger->error("Couldn't fetch Smarty template", [
                "exception" => $e->getMessage(),
                "hook"      => "hookDisplayOrderConfirmation1",
                "id_order"  => $Order->id,
            ]);
        }

        return "";
    }

    /**
     * Add fetch label modal to the end of the BO order list
     */
    public function hookDisplayAdminEndContent(array $params): ?string
    {
        if ($this->context->controller->controller_name !== "AdminOrders") {
            return null;
        }

        try {
            /** @var Twig\Environment $twig */
            $twig = SymfonyContainer::getInstance()->get("twig");
            return $twig->render("@Modules/vg_postnord/views/templates/admin/fetch_label_modal.html.twig");
        } catch (Exception $e) {
            $this->logger->error("Could not render Twig template", [
                "exception" => $e->getMessage(),
                "hook"      => "displayAdminEndContent"
            ]);

            return null;
        }
    }
}
