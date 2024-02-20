<?php
namespace components;
use banktwins\GingerBankOrderBuilder;

/**
 * Class GingerHelper
 */
class GingerHelper
{

    /**
     * Order statuses
     */
    const GINGER_STATUS_EXPIRED = 'expired';
    const GINGER_STATUS_PROCESSING = 'processing';
    const GINGER_STATUS_COMPLETED = 'completed';
    const GINGER_STATUS_CANCELLED = 'cancelled';
    const GINGER_STATUS_ERROR = 'error';
    const GINGER_STATUS_CAPTURED = 'captured';

    /**
     * @var string
     */
    protected $paymentMethod;

    /**
     * @param string $paymentMethod
     */
    public function __construct($paymentMethod)
    {
        $this->paymentMethod = $paymentMethod;
    }

    /**
     * Method maps order status to OpenCart specific
     *
     * @param string $gingerOrderStatus
     * @return string
     */
    public function getOrderStatus($gingerOrderStatus, $config)
    {
        switch ($gingerOrderStatus) {
            case GingerHelper::GINGER_STATUS_EXPIRED:
                $orderStatus = $config->get('payment_ginger_order_status_id_expired');
                break;
            case GingerHelper::GINGER_STATUS_PROCESSING:
                $orderStatus = $config->get('payment_ginger_order_status_id_processing');
                break;
            case GingerHelper::GINGER_STATUS_COMPLETED:
                $orderStatus = $config->get('payment_ginger_order_status_id_completed');
                break;
            case GingerHelper::GINGER_STATUS_CANCELLED:
                $orderStatus = $config->get('payment_ginger_order_status_id_cancelled');
                break;
            case GingerHelper::GINGER_STATUS_ERROR:
                $orderStatus = $config->get('payment_ginger_order_status_id_error');
                break;
            case GingerHelper::GINGER_STATUS_CAPTURED:
                $orderStatus = $config->get('payment_ginger_order_status_id_captured');
                break;
            default:
                $orderStatus = $config->get('payment_ginger_order_status_id_new');
                break;
        }

        return $orderStatus;
    }

    /**
     * Method prepares Ajax response for processing page
     *
     * @param object $paymentMethod
     */
    public function checkStatusAjax($paymentMethod)
    {
        $orderId = $paymentMethod->request->get['order_id'];
        $gingerOrder = $paymentMethod->gingerClient->getOrder($orderId);

        if ($gingerOrder['status'] == "processing" || $gingerOrder['status'] == "new")
        {
            $response = [
                'redirect' => false
            ];
        } else {
            $response = [
                'redirect' => true
            ];
        }

        die(json_encode($response));
    }

    /**
     * @param $paymentMethod
     * @return array
     */
    public function getPageData($paymentMethod)
    {
        $paymentMethod->load->language('extension/payment/'.$this->paymentMethod);
        $paymentMethod->load->language('checkout/success');
        $paymentMethod->load->language('extension/payment/ginger_common');

        $orderInfo = [
            'order_id' => $this->getOrderIdFromPaymentMethod($paymentMethod),
        ];

        $orderBuilder = new GingerBankOrderBuilder($orderInfo, $paymentMethod);

        return [
            'breadcrumbs' => $this->getBreadcrumbs($paymentMethod),
            'fallback_url' => $this->getPendingUrl($paymentMethod),
            'callback_url' => $this->getCallbackUrl($paymentMethod),

            'header' => $paymentMethod->load->controller('common/header'),
            'footer' => $paymentMethod->load->controller('common/footer'),
            'column_left' => $paymentMethod->load->controller('common/column_left'),
            'column_right' => $paymentMethod->load->controller('common/column_right'),
            'content_top' => $paymentMethod->load->controller('common/content_top'),
            'content_bottom' => $paymentMethod->load->controller('common/content_bottom'),

            'order_description_text' => $orderBuilder->getOrderDescription(),
            'text_processing' => $paymentMethod->language->get('text_processing'),
            'processing_message' => $paymentMethod->language->get('processing_message'),
            'pending_text' => $paymentMethod->language->get('pending_text'),
            'pending_message' => $paymentMethod->language->get('pending_message'),
            'pending_message_sub' => $paymentMethod->language->get('pending_message_sub'),
            'button_continue' => $paymentMethod->language->get('button_continue'),

            'continue' => $paymentMethod->url->link('common/home'),
        ];
    }

    /**
     * @param $paymentMethod
     * @return string
     */
    protected function getOrderIdFromPaymentMethod($paymentMethod)
    {
        $gingerOrder = $paymentMethod->gingerClient->getOrder($paymentMethod->request->get['order_id']);
        return (!empty($gingerOrder) && $gingerOrder['merchant_order_id'] !== null) ? $gingerOrder['merchant_order_id'] : '';
    }

    /**
     * @param $paymentMethod
     * @param int $orderId
     * @param string $returnType
     * @param string $errorReason
     * @return string
     */
    public function getReturnURL($paymentMethod, $orderId, $returnType, $errorReason = ''): string
    {
        return htmlspecialchars_decode(
            $paymentMethod->url->link(
                'extension/payment/ginger_return_page',
                [
                    'order_id' => $orderId,
                    'return_type' => $returnType,
                    'error_reason' => $errorReason
                ]
            )
        );
    }

    /**
     * @param $paymentMethod
     * @return array
     */
    public function getBreadcrumbs($paymentMethod)
    {
        return [
            [
                'text' => $paymentMethod->language->get('text_home'),
                'href' => $paymentMethod->url->link('common/home')
            ],
            [
                'text' => $paymentMethod->language->get('text_basket'),
                'href' => $paymentMethod->url->link('checkout/cart')
            ],
            [
                'text' => $paymentMethod->language->get('text_checkout'),
                'href' => $paymentMethod->url->link('checkout/checkout', '', true)
            ]
        ];
    }

    /**
     * @param $paymentMethod
     * @return string
     */
    public function getCallbackUrl($paymentMethod)
    {
        return htmlspecialchars_decode(
            $paymentMethod->url->link(
                'extension/payment/'.$this->paymentMethod.'/callback',
                ['order_id' => $paymentMethod->request->get['order_id']]
            )
        );
    }

    /**
     * @param $paymentMethod
     * @return string
     */
    public function getProcessingUrl($paymentMethod)
    {
        return htmlspecialchars_decode(
            $paymentMethod->url->link(
                'extension/payment/'.$this->paymentMethod.'/processing',
                ['order_id' => $paymentMethod->request->get['order_id']]
            )
        );
    }

    /**
     * @param $paymentMethod
     * @return string
     */
    public function getPendingUrl($paymentMethod)
    {
        return htmlspecialchars_decode(
            $paymentMethod->url->link(
                'extension/payment/'.$this->paymentMethod.'/pending',
                ['order_id' => $paymentMethod->request->get['order_id']]
            )
        );
    }

    /**
     * @param $ipList
     * @return bool
     */
    public static function ipIsEnabled($ipList)
    {
        if (strlen($ipList) > 0)
        {
            $ipWhitelist = array_map('trim', explode(',', $ipList));

            if (!in_array(filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP), $ipWhitelist)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param $countryList
     * @return bool
     */
    public static function countryValidator($countryList, $billingAddress)
    {
        if (!$countryList) return true;
        $arrayCountryList = array_map('trim', explode(',', $countryList));
        return in_array($billingAddress, $arrayCountryList);

    }

    /**
     * Obtain GINGER Online order id from order history.
     *
     * @param array $orderHistory
     * @return mixed
     */
    public static function searchHistoryForOrderKey(array $orderHistory)
    {
        foreach ($orderHistory as $history)
        {
            preg_match('/\w{8}-\w{4}-\w{4}-\w{4}-\w{12}/', $history['comment'], $orderKey);
            if (count($orderKey) > 0) return $orderKey[0];
        }
        return false;
    }
}
