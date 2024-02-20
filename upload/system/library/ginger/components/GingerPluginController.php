<?php
namespace components;
use banktwins\GingerBankOrderBuilder;
use banktwins\GingerBankHelper;
use banktwins\GingerBankClientBuilder;
use interfaces\GingerCustomerPersonalInformation;
use interfaces\GingerIdentificationPay;
use interfaces\GingerIssuers;
use interfaces\GingerTestAPIKey;

class GingerPluginController extends \Controller
{

    const PLUGIN_VERSION = "1.0.0";

    public $gingerClient;
    public $gingerHelper;
    public $gingerOrderBuilder;

    /**
     * @param $registry
     */
    public function __construct($registry)
    {
        parent::__construct($registry);

        $gingerModuleName = str_replace(GingerBankConfig::BANK_PREFIX,'ginger', $this->paymentName);
        $this->gingerHelper = new GingerBankHelper($gingerModuleName);

        if ($this instanceof GingerTestAPIKey)
        {
            $testApiKey = $this->config->get('payment_'.$gingerModuleName.'_'.'test_api_key') ?: null;
        }

        $apiKey = $testApiKey ?? $this->config->get('payment_ginger_api_key');

        $this->gingerClient = GingerBankClientBuilder::getClient(
            $apiKey,
            $this->config->get('payment_ginger_bundle_cacert') ? true : false
        );
    }



    /**
     * Index Action
     * @return mixed
     */
    public function index()
    {
        $gingerModuleName = str_replace(GingerBankConfig::BANK_PREFIX,'ginger', $this->paymentName);
        $this->language->load('extension/payment/'.$gingerModuleName);

        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['action'] = $this->url->link('extension/payment/'.$gingerModuleName.'/confirm');

        if ($this instanceof GingerIssuers)
        {
            $data['text_select_bank'] = $this->language->get('text_select_bank');
            $data['text_choose_bank_issuer'] = $this->language->get('text_choose_bank_issuer');
            $data['text_error_invalid_selected_issuer'] = $this->language->get('text_error_invalid_selected_issuer');
            $data['issuers'] = $this->gingerClient->getIdealIssuers();
        }

        if ($this instanceof GingerIdentificationPay)
        {
            $this->load->model('checkout/order');
        }

        if ($this instanceof GingerCustomerPersonalInformation)
        {
            $data['text_error_please_accept_tc'] = $this->language->get('error_please_accept_tc');
            $data['text_error_invalid_dob'] = $this->language->get('error_invalid_dob');
            $data['text_terms_and_conditions'] = $this->language->get('text_terms_and_conditions');
            $data['text_i_accept'] = $this->language->get('text_i_accept');
            $data['text_please_enter_dob'] = $this->language->get('text_please_enter_dob');
            $data['text_please_select_gender'] = $this->language->get('text_please_select_gender');
            $data['text_please_select_gender_male'] = $this->language->get('text_please_select_gender_male');
            $data['text_please_select_gender_female'] = $this->language->get('text_please_select_gender_female');
            $data['terms_conditions_url'] = ($this->session->data['payment_address']['iso_code_2'] == 'NL') ? static::TERMS_CONDITION_URL_NL : static::TERMS_CONDITION_URL_EN;
        }

        return $this->load->view('extension/payment/'.$gingerModuleName, $data);
    }


    /**
     * Order Confirm Action
     */
    public function confirm()
    {
        $gingerModuleName = str_replace(GingerBankConfig::BANK_PREFIX,'ginger', $this->paymentName);

        try {
            $this->load->model('checkout/order');
            $orderInfo = $this->model_checkout_order->getOrder($this->session->data['order_id']);

            if (!$orderInfo) return true;

            $this->gingerOrderBuilder = new GingerBankOrderBuilder($orderInfo, $this);
            $gingerOrder = $this->gingerClient->createOrder($this->gingerOrderBuilder->getBuiltOrder());
            if ($gingerOrder['status'] == 'error')
            {
                $this->language->load('extension/payment/'.$gingerModuleName);
                $this->session->data['error'] = current($gingerOrder['transactions'])['customer_message'];
                $this->session->data['error'] .= $this->language->get('error_another_payment_method');
                $this->response->redirect($this->url->link('checkout/checkout'));
            }

            if ($gingerOrder['status'] == 'cancelled')
            {
                $this->response->redirect($this->gingerHelper->getFailureUrl($this, $this->session->data['order_id']));
            }

            if ($this instanceof GingerIdentificationPay)
            {
                $this->session->data['bank_information'] = $this->gingerIdentificationProcess($gingerOrder);
                $this->response->redirect($this->gingerHelper->getReturnURL($this, $orderInfo['order_id'],'success'));
            }

            $this->response->redirect(current($gingerOrder['transactions'])['payment_url']);
        } catch (\Exception $e) {
            $this->session->data['error'] = $e->getMessage();
            $this->response->redirect($this->url->link('checkout/checkout'));
        }
    }

    public function gingerIdentificationProcess($gingerOrder)
    {
        $gingerPaymentReference = current($gingerOrder['transactions'])['payment_method_details']['reference'];

        $this->model_checkout_order->addOrderHistory(
            $gingerOrder['merchant_order_id'],
            $this->gingerHelper->getOrderStatus($gingerOrder['status'], $this->config),
            GingerBankConfig::BANK_LABEL.' Bank Transfer order: '.$gingerOrder['id'],
            true
        );

        $this->model_checkout_order->addOrderHistory(
            $gingerOrder['merchant_order_id'],
            $this->gingerHelper->getOrderStatus($gingerOrder['status'], $this->config),
            GingerBankConfig::BANK_LABEL.' Bank Transfer Reference ID: '.$gingerPaymentReference,
            true
        );

        $gingerOrderIBAN = current($gingerOrder['transactions'])['payment_method_details']['creditor_iban'];
        $gingerOrderBIC = current($gingerOrder['transactions'])['payment_method_details']['creditor_bic'];
        $gingerOrderHolderName = current($gingerOrder['transactions'])['payment_method_details']['creditor_account_holder_name'];
        $gingerOrderHolderCity = current($gingerOrder['transactions'])['payment_method_details']['creditor_account_holder_city'];

        return [
            'ginger_payment_reference' => $gingerPaymentReference,
            'ginger_iban' => $gingerOrderIBAN,
            'ginger_bic' => $gingerOrderBIC,
            'ginger_account_holder' => $gingerOrderHolderName,
            'ginger_residence' => $gingerOrderHolderCity,
        ];

    }

    /**
     * Callback Action
     */
    public function callback()
    {
        $this->load->model('checkout/order');
        $gingerOrder = $this->gingerClient->getOrder(filter_input(INPUT_GET,'order_id',FILTER_SANITIZE_STRING));
        $orderInfo = $this->model_checkout_order->getOrder($gingerOrder['merchant_order_id']);
        if (!$orderInfo) return true;

        $this->model_checkout_order->addOrderHistory(
            $gingerOrder['merchant_order_id'],
            $this->gingerHelper->getOrderStatus($gingerOrder['status'], $this->config),
            GingerBankConfig::BANK_LABEL.' order: '.$gingerOrder['id'],
            true
        );
        if ($gingerOrder['status'] == 'completed') {
            $this->response->redirect($this->gingerHelper->getReturnUrl($this, $orderInfo['order_id'], 'success'));
        } elseif ($gingerOrder['status'] == 'processing' || $gingerOrder['status'] == 'new') {
            $this->response->redirect($this->gingerHelper->getProcessingUrl($this));
        } else {
            $errorReason = current($gingerOrder['transactions'])['customer_message'] ?? '';
            $this->response->redirect($this->gingerHelper->getReturnUrl($this, $orderInfo['order_id'], 'failure',$errorReason));
        }
    }

    /**
     * Method is an event trigger for capturing the Payment method shipped status.
     *
     * @param $route
     * @param $data
     */
    public function capture($route, $data)
    {
        $this->load->model('account/order');
        $this->load->model('checkout/order');

        try {
            $gingerOrderID = GingerBankHelper::searchHistoryForOrderKey(
                $this->model_account_order->getOrderHistories(filter_input(INPUT_GET,'order_id',FILTER_SANITIZE_STRING))
            );

            if (!$gingerOrderID) return true;

            $order = $this->model_checkout_order->getOrder(filter_input(INPUT_GET,'order_id',FILTER_SANITIZE_STRING));

            if ($order['payment_code'] == str_replace(GingerBankConfig::BANK_PREFIX,'ginger',$this->paymentName))
            {
                if ($order['order_status'] == 'Shipped')
                {
                    $gingerOrder = $this->gingerClient->getOrder($gingerOrderID);
                    if (in_array('has-captures',$gingerOrder['flags'])) return true;
                    $transactionID = current($gingerOrder['transactions']) ? current($gingerOrder['transactions'])['id'] : null;
                    $this->gingerClient->captureOrderTransaction($gingerOrder['id'], $transactionID);
                };
            }

        } catch (\Exception $e) {
            $this->session->data['error'] = $e->getMessage();
        }
    }

    /**
     * Pending order processing page
     *
     * @return mixed
     */
    public function processing()
    {

        if (filter_input(INPUT_POST,'processing',FILTER_SANITIZE_STRING)) $this->gingerHelper->checkStatusAjax($this);

        return $this->response->setOutput(
            $this->load->view(
                'extension/payment/ginger_processing',
                $this->gingerHelper->getPageData($this)
            )
        );
    }

    /**
     * Pending order processing page
     *
     * @return mixed
     */
    public function pending()
    {
        $this->cart->clear();

        return $this->response->setOutput(
            $this->load->view(
                'extension/payment/ginger_pending',
                $this->gingerHelper->getPageData($this)
            )
        );
    }


    /**
     * Webhook action is called by API when transaction status is updated
     *
     * @return void
     */
    public function webhook()
    {
        $this->load->model('checkout/order');
        $webhookData = json_decode(file_get_contents('php://input'), true);

        if ($webhookData['event'] != 'status_changed') exit();

        $gingerOrder = $this->gingerClient->getOrder($webhookData['order_id']);
        $orderInfo = $this->model_checkout_order->getOrder($gingerOrder['merchant_order_id']);

        if (!$orderInfo) exit();

        $this->model_checkout_order->addOrderHistory(
            $gingerOrder['merchant_order_id'],
            $this->gingerHelper->getOrderStatus($gingerOrder['status'], $this->config),
            'Status changed for order: '.$gingerOrder['id'],
            true
        );
    }

}