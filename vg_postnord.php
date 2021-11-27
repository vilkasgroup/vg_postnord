<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class Vg_postnord extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'vg_postnord';
        $this->tab = 'shipping_logistics';
        $this->version = '0.0.1';
        $this->author = 'Vilkas Group Oy';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Postnord');
        $this->description = $this->l('Postnord shipping for your Prestashop');

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        Configuration::updateValue('VG_POSTNORD_DEBUG_MODE', false);
        Configuration::updateValue('VG_POSTNORD_HOST', '');
        Configuration::updateValue('VG_POSTNORD_APIKEY', '');

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('displayCarrierExtraContent');
    }

    public function uninstall()
    {
        Configuration::deleteByName('VG_POSTNORD_DEBUG_MODE');
        Configuration::deleteByName('VG_POSTNORD_HOST');
        Configuration::deleteByName('VG_POSTNORD_APIKEY');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitVg_postnordModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
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
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Debug mode'),
                        'name' => 'VG_POSTNORD_DEBUG_MODE',
                        'is_bool' => true,
                        'desc' => $this->l('Write more debug logs'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'desc' => $this->l('Get this infromation from Postnord. Usually something like: atapi2.postnord.com'),
                        'name' => 'VG_POSTNORD_HOST',
                        'label' => $this->l('Postnord hostname'),
                    ),
                    array(
                        'type' => 'text',
                        'name' => 'VG_POSTNORD_APIKEY',
                        'desc' => $this->l('Get this information from Postnord. Something like abc123123123123abc123'),
                        'label' => $this->l('Postnord apikey'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'VG_POSTNORD_DEBUG_MODE' => Configuration::get('VG_POSTNORD_DEBUG_MODE'),
            'VG_POSTNORD_HOST' => Configuration::get('VG_POSTNORD_HOST'),
            'VG_POSTNORD_APIKEY' => Configuration::get('VG_POSTNORD_APIKEY'),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
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
        /* Place your code here. */
    }
}
