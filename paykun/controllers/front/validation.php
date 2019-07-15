<?php

function is_not_17(){
	return version_compare(_PS_VERSION_, '1.7', '<');
}

class PaykunvalidationModuleFrontController extends ModuleFrontController
{
	public $ssl = true;
	private $template_data =array();
	public $display_column_left = false;
    private $merchantId;
    private $accessToken;
    private $isLive;
	public function postProcess()
	{

		if ($this->context->cart->id_customer == 0 || $this->context->cart->id_address_delivery == 0 || $this->context->cart->id_address_invoice == 0 || !$this->module->active)
        {
            Tools::redirectLink(__PS_BASE_URI__.'order.php?step=1');
        }

        # prepare logger
        $logger = new FileLogger(0); //0 == debug level, logDebug() won’t work without this.
        if (is_not_17()){
            $logger->setFilename(_PS_ROOT_DIR_ . "/log/pk.log");
        }
        else{
            $logger->setFilename(_PS_ROOT_DIR_ . "/app/logs/pk.log");
        }
        $logger->logDebug("Creating Paykun order for  ".$this->context->cart->id);

		$customer = new Customer($this->context->cart->id_customer);

		if (!Validate::isLoadedObject($customer))
			Tools::redirectLink(__PS_BASE_URI__.'order.php?step=1');

		if (Tools::getValue('payment-id')) {

            $transactionId = Tools::getValue('payment-id');
		    $this->merchantId = Configuration::get('paykun_merchant_id');
            $this->accessToken = Configuration::get('paykun_access_token');
            $this->isLive = (Configuration::get('paykun_is_live') == 1) ? true: false;

		    $response = $this->getTransactionInfo($transactionId);

            if(isset($response['status']) && $response['status'] == "1" || $response['status'] == 1 ) {

                $payment_status = $response['data']['transaction']['status'];
                $order_id = $response['data']['transaction']['custom_field_1'];

                #Match order id with cart

                if($this->context->cart->id != $order_id) {
                    $logger->logDebug("Cart ID sent to Intamojo ($order_id) doesn't match with current cart id (".$this->context->cart->id.")");
                    Tools::redirectLink($this->context->link->getPageLink('order',true)."?step=1");
                }

                $cart = new Cart(intval($order_id));
                $amount = $cart->getOrderTotal(true,Cart::BOTH);

                //if(Configuration::get('Paykun_ENABLE_LOG'))
                {
                    $log_entry = "Reponse Type: Process Response (DEFAULT)". PHP_EOL;
                    $log_entry .= "Reponse status: " . print_r($response, true) .PHP_EOL.PHP_EOL;
                    $logger->logDebug($log_entry);
                }

                $total = $this->context->cart->getOrderTotal(true, Cart::BOTH);

                $extra_vars = Array();
                $extra_vars['transaction_id'] = $transactionId;

                if($payment_status === "Success") { //Transaction is success

                    //if(1) { //Transaction is success

                    $resAmout = $response['data']['transaction']['order']['gross_amount'];

                    //if(Configuration::get('Paykun_ENABLE_LOG')){
                        $log_entry = "Reponse Type: Process Response (DEFAULT)". PHP_EOL;
                        $log_entry .= "amount matching responseAmount=$resAmout And  Order Amount = ".$amount . print_r($payment_status, true) .PHP_EOL.PHP_EOL;
                        $logger->logDebug($log_entry);
                    //}

                    if((intval($amount)	== intval($resAmout))) {
                        try
                        {
                            $logger->logDebug("Payment for $transactionId was credited.");
                            $this->module->validateOrder($this->context->cart->id , _PS_OS_PAYMENT_, $total, 'PayKun', NULL, $extra_vars, NULL, false, $customer->secure_key, NULL);
                            Tools::redirectLink(__PS_BASE_URI__.'index.php?controller=order-detail&id_order='.(int)$this->module->currentOrder);
                        } catch (Exception $e) {
                            echo "<pre>";
                            print_r($e->getTrace()) ;
                            exit;
                        }

                    }
                    else {
                        // Order mismatch occur //

                        $message = "Order Mismatch Occur with Payment Id = $transactionId. Please try again. order status changed to 'failed'";
                        $logger->logDebug($message);

                        $logger->logDebug("Payment for $transactionId failed.");
                        $cart_id = $this->context->cart->id;
                        $this->module->validateOrder($this->context->cart->id , _PS_OS_ERROR_, $total, 'PayKun', NULL, $extra_vars, NULL, false, $customer->secure_key, NULL);

                        $this->context->cart = new Cart($cart_id);
                        $duplicated_cart = $this->context->cart->duplicate();
                        $this->context->cart = $duplicated_cart['cart'];
                        $this->context->cookie->id_cart = (int)$this->context->cart->id;

                        Tools::redirectLink($this->context->link->getPageLink('order',true));

                    }
                }
                else {

                    //Transaction failed

                    $logger->logDebug("Payment for $transactionId failed.");
                    $cart_id = $this->context->cart->id;
                    $this->module->validateOrder($this->context->cart->id , _PS_OS_CANCELED_, $total, 'PayKun', NULL, $extra_vars, NULL, false, $customer->secure_key, NULL);

                    $this->context->cart = new Cart($cart_id);
                    $duplicated_cart = $this->context->cart->duplicate();
                    $this->context->cart = $duplicated_cart['cart'];
                    $this->context->cookie->id_cart = (int)$this->context->cart->id;

                    Tools::redirectLink($this->context->link->getPageLink('order',true));
                }
            }

        }

	}

	/**
	 * @see FrontController::initContent()
	 */
	public function initContent()
	{

        global $cookie;
        $currency = new CurrencyCore($cookie->id_currency);
        $currency_iso_code = $currency->iso_code;


        require_once(dirname(__FILE__).'/../../lib/Paykun/Payment.php');
	    $this->isLive = (Configuration::get('paykun_is_live') == 1) ? true: false;
	    $pkData = $this->prepareData();

		$obj = new \Paykun\Payment($pkData['merchant_id'], $pkData['access_token'], $pkData['enc_key'], $this->isLive, true);

        $obj->initOrder($pkData['pkorderId'], $pkData['purpose'], $pkData['amount'], $pkData['success_url'], $pkData['failed_url']);
        $obj->addCustomer($pkData['name'],$pkData['email'],$pkData['mobile']);

        $obj->addBillingAddress('','','','','');
        $obj->addShippingAddress('','','','','');
        $obj->setCustomFields(['udf_1' => $pkData['orderId']]);
        $reqData = $obj->submit();

        /*unset($reqData['pageTitle']);
        unset($reqData['gateway_url']);*/

		$temp_data = array(
			'checkout_label' => Configuration::get('paykun_checkout_label'),
            'pk_data' => $reqData,
            'currency_code' => $currency_iso_code
		);
		$this->template_data = array_merge($this->template_data, $temp_data);
		$this->context->smarty->assign($this->template_data);
		$this->display_column_left = false;
		$this->display_column_right = false;
		if(is_not_17()){
			$this->setTemplate('validation_old.tpl');
		}
		else{
			$this->setTemplate('module:Paykun/views/templates/front/validation_new.tpl');
		}
		parent::initContent();
	}

    private function getDefaultCallbackUrl(){
        return $this->context->link->getModuleLink('paykun','validation');
    }

	protected function prepareData () {

        $customer = new Customer((int)$this->context->cart->id_customer);
        $address = new Address((int)$this->context->cart->id_address_invoice);
        $total = $this->context->cart->getOrderTotal(true, Cart::BOTH);

        $productsPurpose = $this->context->cart->getProducts();
        if(sizeof($productsPurpose) > 0) {
            $productsPurpose = $productsPurpose[0]['name'];
        } else {
            $productsPurpose = "Purchase Payment";
        }

        $mobile = '';
        if(isset($address->phone_mobile) && trim($address->phone_mobile) != "")
            $mobile = $address->phone_mobile;

        $data['merchant_id'] 	= Configuration::get('paykun_merchant_id');
        $data['access_token'] 	= Configuration::get('paykun_access_token');
        $data['enc_key'] 	    = Configuration::get('paykun_enc_key');
        $data['name'] 			= Tools::substr(trim((html_entity_decode($customer->firstname . ' ' . $customer->lastname, ENT_QUOTES, 'UTF-8'))), 0, 20);
        $data['email'] 			= Tools::substr($customer->email, 0, 75);
        $data['amount'] 		= $total;
        $data['mobile'] 		= $mobile;
        $data['success_url'] 	= $this->getDefaultCallbackUrl();
        $data['failed_url'] 	= $this->getDefaultCallbackUrl();
        $data['pkorderId']      = time()."-".$this->context->cart->id;
        $data['orderId']        = $this->context->cart->id;
        $data['purpose']        = $productsPurpose;
        return $data;
    }

    private function getTransactionInfo($paymentId) {
        try {
            if($this->isLive == true) {
                $cUrl        = 'https://api.paykun.com/v1/merchant/transaction/' . $paymentId . '/';
            } else {
                $cUrl        = 'https://sandbox.paykun.com/api/v1/merchant/transaction/' . $paymentId . '/';
            }
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $cUrl);
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("MerchantId:$this->merchantId", "AccessToken:$this->accessToken"));
            if( isset($_SERVER['HTTPS'] ) ) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
            } else {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            }
            $response       = curl_exec($ch);
            $error_number   = curl_errno($ch);
            $error_message  = curl_error($ch);
            $res = json_decode($response, true);
            curl_close($ch);
            return ($error_message) ? null : $res;
        } catch (\Paykun\Errors\ValidationException $e) {
            throw new \Paykun\Errors\ValidationException("Server couldn't respond, ".$e->getMessage(), $e->getCode(), null);
            return null;
        }
    }
}
