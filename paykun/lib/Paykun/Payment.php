<?php

namespace Paykun;

require_once 'Validator.php';
require_once 'Crypto.php';
require_once 'Errors/ValidationException.php';
require_once 'Errors/ErrorCodes.php';

use Paykun\Errors\ErrorCodes;
use Paykun\Errors\ValidationException;

class Payment {

    const GATEWAY_URL_PROD = "https://checkout.paykun.com/payment";
    const GATEWAY_URL_DEV = "https://sandbox.paykun.com/payment";
    const PAGE_TITLE = "Processing Payment...";

    private $merchantId;
    private $accessToken;
    private $encryptionKey;
    private $orderId;
    private $purpose;
    private $amount;
    private $successUrl;
    private $failureUrl;
    private $country;
    private $state;
    private $city;
    private $pinCode;
    private $addressString;
    private $billingCountry;
    private $billingState;
    private $billingCity;
    private $billingPinCode;
    private $billingAddressString;
    private $twig;
    private $isLive;
    private $isPassedValidationForConstructor = false;
    private $isPassedValidationForInitOrder = false;
    private $isPassedValidationForCustomer = false;
    private $isPassedValidationForShipping = false;
    private $isPassedValidationForBilling = false;
    private $isCustomRenderer = false;

    public $udf_1;
    public $udf_2;
    public $udf_3;
    public $udf_4;
    public $udf_5;
    private $log;
    private $currency = 'INR';


    /**
     * Payment constructor.
     * @param string $mid           => Id of the Merchant
     * @param string $accessToken   => Access token
     * @param string $encKey        => Encryption key
     * @param bool $isLive          => Sandbox or production mode flag
     * @throws Errors\ValidationException
     */

    public function __construct($mid, $accessToken, $encKey, $isLive = true, $isCustomTemplate = false) {

        if (Validator::VALIDATE_MERCHANT_ID($mid)) {

            throw new ValidationException(ErrorCodes::INVALID_MERCHANT_ID_STRING,
                ErrorCodes::INVALID_MERCHANT_ID_CODE, null);
        }

        if (\Paykun\Validator::VALIDATE_ACCESS_TOKEN($accessToken)) {

            throw new ValidationException(ErrorCodes::INVALID_ACCESS_TOKEN_STRING,
                ErrorCodes::INVALID_ACCESS_TOKEN_CODE, null);
        }

        if (\Paykun\Validator::VALIDATE_ENCRYPTION_KEY($encKey)) {

            throw new ValidationException(ErrorCodes::INVALID_API_SECRETE_STRING,
                ErrorCodes::INVALID_API_SECRETE_CODE, null);
        }

        $this->merchantId       = $mid;
        $this->accessToken      = $accessToken;
        $this->encryptionKey    = $encKey;
        $this->isLive           = $isLive;
        $this->isPassedValidationForConstructor = true;
        $this->isCustomRenderer = $isCustomTemplate;

        if($this->isCustomRenderer == false) {
            $loader = new \Twig_Loader_Filesystem(__DIR__.'/template');

            $this->twig = new \Twig_Environment($loader);
        }


    }

    /*Remove this function if it's not wordpress*/
    private function addLog($message) {

        $this->log->add( 'paykun-payment', $message);

    }

    /**
     * @param string $orderId               => Pay for the order id given by the Merchant
     * @param string $purpose               => Detail description for what you are paying
     * @param string $amount                => Amount to be paid
     * @param string $successUrl            => Redirect to the sucsess page once payment is done.
     * @param string $failureUrl            => Redirect to the failed page once payment is not done.
     * @return $this
     * @throws Errors\ValidationException
     */


    public function initOrder ($orderId, $purpose, $amount, $successUrl, $failureUrl, $currency = 'INR') {

        if (\Paykun\Validator::VALIDATE_ORDER_NUMBER($orderId)) {

            throw new ValidationException(ErrorCodes::INVALID_ORDER_ID_STRING,
                ErrorCodes::INVALID_ORDER_ID_CODE, null);
        }

        if (\Paykun\Validator::VALIDATE_PURPOSE($purpose)) {

            throw new ValidationException(ErrorCodes::INVALID_PURPOSE_STRING,
                ErrorCodes::INVALID_PURPOSE_CODE, null);
        }

        if (\Paykun\Validator::VALIDATE_AMOUNT($amount)) {

            throw new ValidationException(ErrorCodes::INVALID_AMOUNT_STRING,
                ErrorCodes::INVALID_AMOUNT_CODE, null);
        }

        if (\Paykun\Validator::VALIDATE_URL($successUrl)) {

            throw new ValidationException(ErrorCodes::INVALID_SUCCESS_URL_STRING,
                ErrorCodes::INVALID_SUCCESS_URL_CODE, null);
        }

        if (\Paykun\Validator::VALIDATE_URL($failureUrl)) {

            throw new ValidationException(ErrorCodes::INVALID_FAIL_URL_STRING,
                ErrorCodes::INVALID_FAIL_URL_CODE, null);
        }

        $this->orderId      = $orderId;
        $this->purpose      = $purpose;
        $this->amount       = $amount;
        $this->successUrl   = $successUrl;
        $this->failureUrl   = $failureUrl;
        $this->isPassedValidationForInitOrder = true;
        $this->currency = $currency;
        return $this;

    }


    /**
     * @param string $customerName
     * @param string $customerEmail
     * @param string $customerMoNo
     * @return $this
     * @throws Errors\ValidationException
     */

    public function addCustomer($customerName, $customerEmail, $customerMoNo) {

        $this->customerName     = $customerName;
        $this->customerEmail    = $customerEmail;
        $this->customerMoNo     = $customerMoNo;
        $this->isPassedValidationForCustomer = true;
        return $this;

    }


    /**
     * @param string $country
     * @param string $state
     * @param string $city
     * @param string $pinCode
     * @param string $addressString
     */

    public function addShippingAddress($country, $state, $city, $pinCode, $addressString) {
        $errorPrefix = "Shipping ";

        $this->country          = $country;
        $this->state            = $state;
        $this->city             = $city;
        $this->pinCode          = $pinCode;
        $this->addressString    = $addressString;

        $this->isPassedValidationForShipping = true;

    }


    /**
     * @param string $country
     * @param string $state
     * @param string $city
     * @param string $pinCode
     * @param string $addressString
     */

    public function addBillingAddress($country, $state, $city, $pinCode, $addressString) {

        $errorPrefix = "Billing ";

        $this->billingCountry   = $country;
        $this->billingState     = $state;
        $this->billingCity      = $city;
        $this->billingPinCode   = $pinCode;
        $this->billingAddressString = $addressString;
        $this->isPassedValidationForBilling = true;

    }

    public function setCustomFields($fields = null) {

        if($fields != null) {
            if(isset($fields['udf_1'])) {
                $this->udf_1 = $fields['udf_1'];
            }
            if(isset($fields['udf_2'])) {
                $this->udf_2 = $fields['udf_2'];
            }
            if(isset($fields['udf_3'])) {
                $this->udf_3 = $fields['udf_3'];
            }
            if(isset($fields['udf_4'])) {
                $this->udf_4 = $fields['udf_4'];
            }
            if(isset($fields['udf_5'])) {
                $this->udf_5 = $fields['udf_5'];
            }
        }

    }

    /**
     * @param bool $isCustomRender The is render parameter specifies whethere the user want to use custom form for submit or not
     * By default the default template will be used for rendering
     * Set to false for non-composer users
     * @return string
     * @throws Errors\ValidationException
     */
    public function submit() {
        if (
            $this->isPassedValidationForConstructor &&
            $this->isPassedValidationForInitOrder &&
            $this->isPassedValidationForCustomer &&
            $this->isPassedValidationForShipping &&
            $this->isPassedValidationForBilling
        ) {

            $dataArray                      = array();
            $dataArray['order_no']          = $this->orderId;
            $dataArray['product_name']      = $this->purpose;
            $dataArray['amount']            = $this->amount;
            $dataArray['success_url']       = $this->successUrl;
            $dataArray['failure_url']       = $this->failureUrl;
            $dataArray['customer_name']     = $this->customerName;
            $dataArray['customer_email']    = $this->customerEmail;
            $dataArray['customer_phone']    = $this->customerMoNo;
            $dataArray['shipping_address']  = $this->addressString;
            $dataArray['shipping_city']     = $this->city;
            $dataArray['shipping_state']    = $this->state;
            $dataArray['shipping_country']  = $this->country;
            $dataArray['shipping_zip']      = $this->pinCode;
            $dataArray['billing_address']   = $this->billingAddressString;
            $dataArray['billing_city']      = $this->billingCity;
            $dataArray['billing_state']     = $this->billingState;
            $dataArray['billing_country']   = $this->billingCountry;
            $dataArray['billing_zip']       = $this->billingPinCode;
            $dataArray['udf_1']             = $this->udf_1 ? $this->udf_1 : '';
            $dataArray['udf_2']             = $this->udf_2 ? $this->udf_2 : '';
            $dataArray['udf_3']             = $this->udf_3 ? $this->udf_3 : '';
            $dataArray['udf_4']             = $this->udf_4 ? $this->udf_4 : '';
            $dataArray['udf_5']             = $this->udf_5 ? $this->udf_5 : '';
            $dataArray['currency']          = $this->currency;
            $encryptedData = $this->encryptData($dataArray);
            return $this->createForm($encryptedData);
        }

        /*Validation is not passed for all the steps*/
        throw new ValidationException(ErrorCodes::INVALID_DATA_PROVIDED_STRING,
            ErrorCodes::INVALID_DATA_PROVIDED_CODE, null);

    }


    /**
     * @param array $data
     * @return string
     */

    private function encryptData(array $data) {

        $data = array_filter($data);
        ksort($data);

        $dataToPostToPG = "";
        
        foreach ($data as $key => $value)
        {
                if ("" == trim($value) && $value === NULL) {
                } else {
                    $dataToPostToPG = $dataToPostToPG . $key . "::" . ($value) . ";";
                }
        }

        // Removing last 2 characters (::) 
        $dataToPostToPG = substr($dataToPostToPG, 0, -1);
        // Encrypting String

        return \Paykun\Crypto::encrypt($dataToPostToPG, $this->encryptionKey);

    }


    /**
     * @param string $encData
     * @return string
     */

    private function createForm($encData) {

        $formData = array();
        $formData['encrypted_request']  = $encData;
        $formData['merchant_id']        = $this->merchantId;
        $formData['access_token']       = $this->accessToken;
        if ($this->isLive) {
            $formData['gateway_url'] = self::GATEWAY_URL_PROD;
        } else {
            $formData['gateway_url'] = self::GATEWAY_URL_DEV;
        }
        
        $formData['pageTitle'] = self::PAGE_TITLE;
        return $formData;

    }


    /**
     * @param $templateName
     * @param array $parameters
     * @return string
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */

    public function render($templateName, array $parameters = array()) {

        return $this->twig->render($templateName, $parameters);

    }

}

?>