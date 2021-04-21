<?php
if (!defined('_PS_VERSION_'))
	exit;

class paykun extends PaymentModule
{
	private $error_messages;

    /**
     * Paykun constructor.
     */
    public function __construct()
	{
		$this->name = 'paykun';
		$this->tab = 'payments_gateways';
		$this->version = '1.0.0';
		$this->author = 'paykun';
		$this->need_instance = 0;
		$this->controllers = array('validation');
		$this->is_eu_compatible = 1;
		$this->error_messages;
		$this->bootstrap = true;
		$this->currencies = true;
		$this->currencies_mode = 'checkbox';
 
		parent::__construct();

		$this->displayName = $this->l('paykun');
		$this->description = $this->l('Accept Online payments');

		/* For 1.4.3 and less compatibility */
		$updateConfig = array(
		    'PS_OS_CHEQUE',
            'PS_OS_PAYMENT',
            'PS_OS_PREPARATION',
            'PS_OS_SHIPPING',
            'PS_OS_CANCELED',
            'PS_OS_REFUND',
            'PS_OS_ERROR',
            'PS_OS_OUTOFSTOCK',
            'PS_OS_BANKWIRE',
            'PS_OS_PAYPAL',
            'PS_OS_WS_PAYMENT'
        );
		if (!Configuration::get('PS_OS_PAYMENT'))
			foreach ($updateConfig as $u)
				if (!Configuration::get($u) && defined('_'.$u.'_'))
					Configuration::updateValue($u, constant('_'.$u.'_'));
		
		/* Check if cURL is enabled */
		if (!is_callable('curl_exec'))
			$this->warning = $this->l('cURL extension must be enabled on your server to use this module.');
		
	}

	public function install()
	{
		parent::install();
		$this->registerHook('payment');
		$this->registerHook('displayPaymentEU');
		$this->registerHook('paymentReturn');
		Configuration::updateValue('paykun_checkout_label', 'Pay With PayKun');
		if (is_not_17()){
			$logsLocation = _PS_ROOT_DIR_ . "/log/pk.log";
		}
		else{
			$logsLocation = _PS_ROOT_DIR_ . "/app/logs/pk.log";
		}			
		if (!file_exists($logsLocation)) {
			mkdir($logsLocation, 0777, true);
		}
		return true;
	}
	
	
	public function uninstall()
	{
		  parent::uninstall();
		  Configuration::deleteByName('paykun_merchant_id');
		  Configuration::deleteByName('paykun_access_token');
		  Configuration::deleteByName('paykun_enc_key');
		  Configuration::deleteByName('paykun_is_live');
		  Configuration::deleteByName('paykun_checkout_label');

		return true;
	}

	public function hookPayment()
	{
		if (!$this->active)
			return ;
		
		$this->smarty->assign(array(
			'this_path' => $this->_path, //keep for retro compat
			'this_path_paykun' => $this->_path,
			'checkout_label' => $this->l((Configuration::get('paykun_checkout_label')) ? Configuration::get('paykun_checkout_label'): "Pay using Paykun"),
			'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/' . $this->name . '/'
		));
		
		return $this->display(__FILE__, 'payment.tpl');
	}
	
	public function hookDisplayPaymentEU()
	{
		if (!$this->active)
			return ;

		return array(
			'cta_text' => $this->l((Configuration::get('paykun_checkout_label'))?Configuration::get('paykun_checkout_label'):"Pay using Paykun"),
			'logo' => Media::getMediaPath(dirname(__FILE__).'/paykun.png'),
			'action' => $this->context->link->getModuleLink($this->name, 'validation', array('confirm' => true), true)
		); 
		
	}
	
	
	public function getPaykunObject($logger){
		include_once __DIR__. DIRECTORY_SEPARATOR . "lib/Paykun/Payment.php";
		$credentials = $this->getConfigValues();
		$logger->logDebug("Credintials Client ID: $credentials[paykun_client_id] Client Secret : $credentials[paykun_client_secret] TestMode : $credentials[paykun_testmode] ");
		$api  = new \Paykun\Payment($credentials['paykun_merchant_id'],$credentials['paykun_access_token'],$credentials['paykun_enc_key'], false, true);
		return $api;
	}
	
	public function hookPaymentReturn()
	{
		if (!$this->active)
			return ;
		return ;
	}
	
	public function getConfigValues(){
		
		$data = array();
		$data['paykun_merchant_id'] = Configuration::get('paykun_merchant_id');
		$data['paykun_access_token'] = Configuration::get('paykun_access_token');
		$data['paykun_enc_key'] = Configuration::get('paykun_enc_key');
		$data['paykun_is_live'] = Configuration::get('paykun_is_live');
		$data['paykun_checkout_label'] = Configuration::get('paykun_checkout_label');
		return $data;
	}
	
	
	public function validate_data(){
		$this->error_messages = "";

		if(!strval(Tools::getValue('paykun_merchant_id')))
			$this->error_messages .= "Merchant ID is Required<br/>";
		 if(!strval(Tools::getValue('paykun_access_token')))
			$this->error_messages .= "Access Token is Required<br/>";
        if(!strval(Tools::getValue('paykun_enc_key')))
            $this->error_messages .= "Encryption Key is Required<br/>";
		return !$this->error_messages;
	}
	
	# Show Configuration form in admin panel.
	public function getContent()
	{
		$output = null;
		// $order_states = OrderState::getOrderStates((int)$this->context->cookie->id_lang);
		if (Tools::isSubmit('submit'.$this->name))
		{

			$data['paykun_merchant_id']     = strval(Tools::getValue('paykun_merchant_id'));
			$data['paykun_access_token']    = strval(Tools::getValue('paykun_access_token'));
			$data['paykun_enc_key']         = strval(Tools::getValue('paykun_enc_key'));
            $data['paykun_is_live']         = strval(Tools::getValue('paykun_is_live'));
			$data['paykun_checkout_label']  = strval(Tools::getValue('paykun_checkout_label'));

			if ($this->validate_data($data))
			{
				Configuration::updateValue('paykun_merchant_id', $data['paykun_merchant_id']);
				Configuration::updateValue('paykun_access_token', $data['paykun_access_token']);
				Configuration::updateValue('paykun_enc_key', $data['paykun_enc_key']);
                Configuration::updateValue('paykun_is_live', $data['paykun_is_live']);
				Configuration::updateValue('paykun_checkout_label', $data['paykun_checkout_label']);
				$output .= $this->displayConfirmation($this->l('Settings updated'));
			} else {
                $output .= $this->displayError($this->error_messages);
            }

		}
		return $output.$this->displayForm();
	}
	
	public function displayForm()
	{
		// Get default language
		$default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
		 
		// Init Fields form array
		$fields_form =array();
		$fields_form[0]['form'] = array(
			'legend' => array(
				'title' => $this->l('Settings'),
			),
			'input' => array(
					array(
					'type' => 'text',
					'label' => $this->l('Checkout Label'),
					'name' => 'paykun_checkout_label',
			 		'required' => true
				),
				array(
					'type' => 'text',
					'label' => $this->l('Merchant ID'),
					'name' => 'paykun_merchant_id',
					'required' => true
				),

			
				array(
					'type' => 'text',
					'label' => $this->l('Access Token'),
					'name' => 'paykun_access_token',
					'required' => true
				),
                array(
                    'type' => 'text',
                    'label' => $this->l('Encryption Key'),
                    'name' => 'paykun_enc_key',
                    'required' => true
                ),
				array(
				  'type'      => 'radio',                      
				  'label'     => $this->l('Is Live?'),
				  'name'      => 'paykun_is_live',
				  'required'  => true,                         
				  'is_bool'   => true,                         
				  'values'    => array(                        
					array(
					  'id'    => 'active_on',                  
					  'value' => 1,                               
					  'label' => $this->l('Yes')
					),
					array(
					  'id'    => 'active_off',
					  'value' => 0,
					  'label' => $this->l('No')
					)
				  ),
				)
			),
			
			'submit' => array(
				'title' => $this->l('Save'),
				'class' => 'btn btn-default pull-right'
			)
		);
		 
		$helper = new HelperForm();
		 
		// Module, token and currentIndex
		$helper->module = $this;
		$helper->name_controller = $this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
		 
		// Language
		$helper->default_form_language = $default_lang;
		$helper->allow_employee_form_lang = $default_lang;
		 
		// Title and toolbar
		$helper->title = $this->displayName;
		$helper->show_toolbar = true;        // false -> remove toolbar
		$helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
		$helper->submit_action = 'submit'.$this->name;
		
		 
		// Load current value
		$helper->fields_value = $this->getConfigValues();
		 
		return $helper->generateForm($fields_form);
	}

	private function is_not_17(){
		return version_compare(_PS_VERSION_, '1.7', '<');
	}
	
}
