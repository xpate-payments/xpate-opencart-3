<?php
namespace components;

use banktwins\GingerBankOrderBuilder;
use banktwins\GingerBankHelper;
use banktwins\GingerBankClientBuilder;
use interfaces\GingerCapture;
use interfaces\GingerCountryValidation;
use interfaces\GingerIPValidation;
use interfaces\GingerTestAPIKey;

class GingerAdminController extends \Controller
{

    use MultiCurrencyCaching;
    private $gingerClient;

    public function __construct($registry)
    {
        parent::__construct($registry);
        if ($this instanceof GingerTestAPIKey)
        {
            $testApiKey = $this->config->get('payment_'.$this->paymentName.'_'.'test_api_key') ?: null;
        }

        $apiKey = $testApiKey ?? $this->config->get('payment_ginger_api_key');

        if ($apiKey)
        {
            $this->gingerClient = GingerBankClientBuilder::getClient(
                $apiKey,
                $this->config->get('payment_ginger_bundle_cacert') ? true : false
            );
        }
    }
    /**
     * Prefix for fields in admin settings page
     */
    const POST_FIELD_PREFIX = 'payment_';

    /**
     * @var array
     */
    static $update_fields = [
        'api_key',
        'status',
        'sort_order',
        'order_status_id_new',
        'order_status_id_processing',
        'order_status_id_completed',
        'order_status_id_expired',
        'order_status_id_cancelled',
        'order_status_id_error',
        'order_status_id_captured',
        'total',
        'bundle_cacert',
        'ip_filter',
        'test_api_key',
        'country_access'
    ];

    /**
     * @var array
     */
    private $error = array();

    public function install()
    {

        $this->load->model('setting/event');
        $this->load->model('setting/setting');

        $this->model_setting_event->addEvent(
            $this->paymentName.'_ginger_refund_order',
            'admin/model/sale/return/addReturnHistory/after',
            'extension/payment/'.$this->paymentName.'/refund_an_order'
        );

        if($this instanceof GingerCapture)
        {
            $this->model_setting_event->addEvent(
                $this->paymentName.'_edit_order',
                'catalog/controller/api/order/edit/after',
                'extension/payment/'.$this->paymentName.'/capture'
            );

            $this->model_setting_event->addEvent(
                $this->paymentName.'_add_history',
                'catalog/controller/api/order/history/after',
                'extension/payment/'.$this->paymentName.'/capture'
            );
        }

        $this->model_setting_setting->editSetting('payment_'.$this->paymentName, [
            'payment_'.$this->paymentName.'_order_status_id_new' => '1',
            'payment_'.$this->paymentName.'_order_status_id_processing' => '2',
            'payment_'.$this->paymentName.'_order_status_id_error' => '10',
            'payment_'.$this->paymentName.'_order_status_id_cancelled' => '7',
            'payment_'.$this->paymentName.'_order_status_id_expired' => '14',
            'payment_'.$this->paymentName.'_order_status_id_captured' => '3',
            'payment_'.$this->paymentName.'_order_status_id_completed' => '5',
            'payment_'.$this->paymentName.'_country_access' => ($this instanceof GingerCountryValidation) ? 'NL, BE' : ''
        ]);

    }

    public function uninstall()
    {
        $this->load->model('setting/event');

        $this->model_setting_event->deleteEventByCode($this->paymentName.'_ginger_refund_order');

        if ($this instanceof GingerCapture)
        {
            $this->model_setting_event->deleteEventByCode($this->paymentName.'_edit_order');
            $this->model_setting_event->deleteEventByCode($this->paymentName.'_add_history');
        }
    }

    public function index()
    {
        $this->language->load('extension/payment/'.$this->paymentName);
        $this->load->model('setting/setting');
        $this->load->model('localisation/order_status');

        $this->document->setTitle($this->language->get('heading_title'));

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) $this->updateSettings();

        $data = $this->getTemplateData();
        $data = $this->prepareSettingsData($data);

        $data['breadcrumbs'] = $this->getBreadcrumbs();
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $data['ginger_module'] = $this->paymentName;

        $this->response->setOutput($this->load->view('extension/payment/ginger', $data));
    }


    /**
     * @return bool
     */
    protected function validate()
    {
        if ($this instanceof GingerTestAPIKey)
        {
            $testApiKey = $this->request->post[$this->getModuleFieldName('test_api_key')] ?: null;
        }

        if($this->paymentName == 'ginger')
        {
            $apiKey = $this->request->post[$this->getModuleFieldName('api_key')];//get api key
            if (!$apiKey && !isset($testApiKey)) $this->error['missing_api'] = $this->language->get('error_missing_api_key');
        }

        if (!$this->user->hasPermission('modify', 'extension/payment/'.$this->paymentName))
        {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }

    /**
     * Method updates Payment Settings and redirects back to payment plugin page
     */
    protected function updateSettings()
    {
        if ($this->paymentName == 'ginger' && $this->gingerClient)
        {
            $this->cacheCurrencyList();
        }
        $this->model_setting_setting->editSetting(static::POST_FIELD_PREFIX . $this->paymentName, $this->mapPostData());

        $this->session->data['success'] = $this->language->get('text_settings_saved');

        $this->response->redirect(
            $this->url->link('marketplace/extension', 'user_token='.$this->session->data['user_token'] . '&type=payment', true)
        );
    }

    /**
     * @return array
     */
    protected function getTemplateData()
    {

        $data = [
            'heading_title' => $this->language->get('heading_title'),
            'text_edit_ginger' => $this->language->get('text_edit_ginger'),
            'info_help_api_key' => $this->language->get('info_help_api_key'),
            'info_help_total' => $this->language->get('info_help_total'),
            'entry_ginger_api_key' => $this->language->get('entry_ginger_api_key'),
            'entry_order_completed' => $this->language->get('entry_order_completed'),
            'entry_order_new' => $this->language->get('entry_order_new'),
            'entry_order_error' => $this->language->get('entry_order_error'),
            'entry_order_expired' => $this->language->get('entry_order_expired'),
            'entry_order_cancelled' => $this->language->get('entry_order_cancelled'),
            'entry_order_processing' => $this->language->get('entry_order_processing'),
            'entry_order_captured' => $this->language->get('entry_order_captured'),
            'entry_sort_order' => $this->language->get('entry_sort_order'),
            'entry_status' => $this->language->get('entry_status'),
            'entry_ginger_total' => $this->language->get('entry_ginger_total'),
            'entry_country_access' => $this->language->get('entry_country_access'),
            'entry_cacert' =>  $this->language->get('entry_cacert'),
            'text_enabled' => $this->language->get('text_enabled'),
            'text_disabled' => $this->language->get('text_disabled'),
            'button_save' => $this->language->get('text_button_save'),
            'button_cancel' => $this->language->get('text_button_cancel'),
            'text_yes' => $this->language->get('text_yes'),
            'text_no' => $this->language->get('text_no'),
            'action' => $this->url->link(
                'extension/payment/'.$this->paymentName, 'user_token='.$this->session->data['user_token'],
                true
            ),
            'cancel' => $this->url->link(
                'marketplace/extension', 'user_token='.$this->session->data['user_token'] . '&type=payment',
                true
            )
        ];

        $paymentTitle = str_replace('ginger_','',$this->paymentName);

        if ($this instanceof GingerIPValidation)
        {
            $data['info_help_'.$paymentTitle.'_ip_filter'] = $this->language->get('info_help_'.$paymentTitle.'_ip_filter');
            $data['entry_'.$paymentTitle.'_ip_filter'] = $this->language->get('entry_'.$paymentTitle.'_ip_filter');
        }

        if ($this instanceof GingerCountryValidation)
        {
            $data['info_example_country_access'] = $this->language->get('info_example_country_access');
        }

        if ($this instanceof GingerTestAPIKey)
        {
            $data['info_help_'.$paymentTitle.'_test_api_key'] = $this->language->get('info_help_'.$paymentTitle.'_test_api_key');
            $data['entry_'.$paymentTitle.'_test_api_key'] = $this->language->get('entry_'.$paymentTitle.'_test_api_key');
        }

        return $data;
    }

    /**
     * Process and prepare data for configuration page
     *
     * @param array $data
     * @return array
     */
    protected function prepareSettingsData(array $data)
    {
        foreach (static::$update_fields as $fieldToUpdate)
        {
            $moduleFieldName = $this->getModuleFieldName($fieldToUpdate);
            $data[$moduleFieldName] = $this->request->post[$moduleFieldName] ?? $this->config->get($moduleFieldName);
        }

        if (!$this->config->get('payment_ginger_api_key'))
        {
            $data['info_message'] = $this->language->get('info_plugin_not_configured');
        }

        $data['error_missing_api_key'] = $this->error['missing_api'] ?? '';

        return $data;
    }

    /**
     * Generate configuration page breadcrumbs
     *
     * @return array
     */
    protected function getBreadcrumbs()
    {
        return [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/dashboard', 'user_token='.$this->session->data['user_token'], true)
            ],
            [
                'text' => $this->language->get('text_extension'),
                'href' => $this->url->link('marketplace/extension', 'user_token='.$this->session->data['user_token'].'&type=payment', true)
            ],
            [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('extension/payment/'.$this->paymentName,
                    'user_token='.$this->session->data['user_token'], true)
            ]
        ];
    }

    /**
     * @return array
     */
    protected function mapPostData()
    {
        $postFields = [];
        foreach (static::$update_fields as $field)
        {
            $moduleFieldName = $this->getModuleFieldName($field);
            if (array_key_exists($moduleFieldName, $this->request->post))
            {
                $postFields[$moduleFieldName] = $this->request->post[$moduleFieldName];
            }
        }

        return $postFields;
    }

    /**
     * @param  string $fieldName
     * @return string
     */
    protected function getModuleFieldName($fieldName)
    {
        return static::POST_FIELD_PREFIX . $this->paymentName.'_'.$fieldName;
    }


    /**
     * Function refund_an_order - refund ginger order
     */
    public function refund_an_order(){
        try {
            if (filter_input(INPUT_POST,'return_status_id',FILTER_SANITIZE_STRING) != 3) return true;
            $this->load->model('sale/return');
            $this->load->model('localisation/return_reason');

            $returnInfo = $this->model_sale_return->getReturn($this->request->get['return_id']);

            $orderId = $returnInfo['order_id'];
            $this->load->model('sale/order');
            $orderInfo = $this->model_sale_order->getOrder($orderId);
            if ($this->paymentName == $orderInfo['payment_code'])
            {
                $this->language->load('extension/payment/' . $orderInfo['payment_code']);
                if (!$returnInfo) throw new \Exception('Product return information is empty');

                $returnReason = $this->model_localisation_return_reason->getReturnReason($returnInfo["return_reason_id"]);
                $orderProducts = $this->model_sale_order->getOrderProducts($orderId);

                foreach ($orderProducts as $orderProduct)
                {
                    if (!(int) $orderProduct['total']) throw new \Exception($orderInfo['payment_method'].': '.$this->language->get('empty_price'));
                    $amount += (int) $orderProduct['total'];
                }

                $orderHistory = $this->model_sale_order->getOrderHistories($orderId);
                $gingerOrderId = substr(end($orderHistory)['comment'], strpos(end($orderHistory)['comment'], ":") + 2);

                if ($gingerOrderId) $gingerOrder = $this->gingerClient->getOrder($gingerOrderId);
                if (in_array('has-refunds',$gingerOrder['flags'])) return true;

                if ($gingerOrder['status'] != 'completed')
                {
                    throw new \Exception($orderInfo['payment_method'] . ': ' . $this->language->get('wrong_order_status'));
                }
                $orderBuilder = new GingerBankOrderBuilder($orderInfo, $this);

                $refundData = [
                    'amount' => $orderBuilder->getAmountInCents($amount),
                    'description' => 'OrderID: #' . $orderId . ', Reason: ' . $returnReason['name']
                ];

                if ($this instanceof GingerCapture)
                {
                    if (!in_array('has-captures',$gingerOrder['flags']))
                    {
                        throw new \Exception($orderInfo['payment_method'].': '.$this->language->get('order_not_captured'));
                    }
                    $orderInfo['total'] = $amount;
                    $refundData['order_lines'] = $orderBuilder->getOrderLines($orderBuilder->getAmountInCents());
                }

                $gingerRefundOrder = $this->gingerClient->refundOrder($gingerOrder['id'], $refundData);
                if (in_array($gingerRefundOrder['status'], ['error', 'cancelled', 'expired']))
                {
                    if (current($gingerRefundOrder['transactions'])['customer_message'])
                    {
                        throw new \Exception($orderInfo['payment_method'] . ': ' . current($gingerRefundOrder['transactions'])['customer_message']);
                    }
                    throw new \Exception($orderInfo['payment_method'] . ': ' . $this->language->get('refund_not_completed'));
                }
            }

        } catch (\Exception $e) {
            $this->log->write($e->getMessage());
            exit();
        }
    }

}