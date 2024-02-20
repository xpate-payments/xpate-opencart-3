<?php
class ControllerExtensionPaymentGingerReturnPage extends Controller
{
    /**
     * Function Assembles data for a view page
     */
    public function index()
    {
        $returnType = filter_input(INPUT_GET, 'return_type',FILTER_SANITIZE_STRING);

        $this->load->language('checkout/'.$returnType);
        $this->load->language('extension/payment/ginger_common');

        $orderID = filter_input(INPUT_GET, 'order_id',FILTER_SANITIZE_STRING);

        $bankInformation = $this->session->data['bank_information'] ?? '';

        if ($returnType == 'success' && isset($this->session->data['order_id']))
        {
            $this->clearData();
        }

        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_basket'),
            'href' => $this->url->link('checkout/cart')
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_checkout'),
            'href' => $this->url->link('checkout/checkout', '', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_'.$returnType),
            'href' => $this->url->link('checkout/'.$returnType)
        );

        $data['heading_title'] = sprintf($this->language->get('text_your_order_at'), $orderID, $this->config->get('config_name'));

        if($returnType == 'failure'){
            $data['text_message'] = sprintf($this->language->get('text_message'), $this->url->link('information/contact'));
            $errorReason = filter_input(INPUT_GET, 'error_reason',FILTER_SANITIZE_STRING);
            $data['text_message'] .= $errorReason ? sprintf($this->language->get('text_error_reason'),$errorReason) : "";
        }else{
            $data['text_message']  = $this->customer->isLogged()
                ? sprintf($this->language->get('text_customer'), $this->url->link('account/account', '', true), $this->url->link('account/order', '', true), $this->url->link('account/download', '', true), $this->url->link('information/contact'))
                : sprintf($this->language->get('text_guest'), $this->url->link('information/contact'));
        }


        if ($bankInformation)
        {
            $data['text_message'] .= $this->getBankTransferInformation($bankInformation);
        }

        $data['button_continue'] = $this->language->get('button_continue');

        $data['continue'] = $this->url->link('common/home');

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        $this->response->setOutput($this->load->view('common/success', $data));

    }

    /**
     * Function clears data and adds customer's information to activity log
     */
    private function ClearData()
    {
        $this->cart->clear();

        // Add to activity log
        if ($this->config->get('config_customer_activity'))
        {
            $this->load->model('account/activity');

            if ($this->customer->isLogged()) {
                $activity_data = array(
                    'customer_id' => $this->customer->getId(),
                    'name'        => $this->customer->getFirstName() . ' ' . $this->customer->getLastName(),
                    'order_id'    => $this->session->data['order_id']
                );

                $this->model_account_activity->addActivity('order_account', $activity_data);
            } else {
                $activity_data = array(
                    'name'     => $this->session->data['guest']['firstname'] . ' ' . $this->session->data['guest']['lastname'],
                    'order_id' => $this->session->data['order_id']
                );

                $this->model_account_activity->addActivity('order_guest', $activity_data);
            }
        }

        unset($this->session->data['shippems_method']);
        unset($this->session->data['shippems_methods']);
        unset($this->session->data['payment_method']);
        unset($this->session->data['payment_methods']);
        unset($this->session->data['guest']);
        unset($this->session->data['comment']);
        unset($this->session->data['order_id']);
        unset($this->session->data['bank_information']);
        unset($this->session->data['coupon']);
        unset($this->session->data['reward']);
        unset($this->session->data['voucher']);
        unset($this->session->data['vouchers']);
        unset($this->session->data['totals']);
    }

    private function getBankTransferInformation($bankInformation)
    {
        $this->load->language('extension/payment/ginger_banktransfer');

        $data = $this->language->get('text_description'). '<br>';
        $data .= $this->language->get('ginger_bank_details'). '<br>';
        $data .= $this->language->get('ginger_payment_reference'). $bankInformation['ginger_payment_reference']. '<br>';
        $data .= $this->language->get('ginger_iban'). $bankInformation['ginger_iban']. '<br>';
        $data .= $this->language->get('ginger_bic'). $bankInformation['ginger_bic']. '<br>';
        $data .= $this->language->get('ginger_account_holder'). $bankInformation['ginger_account_holder']. '<br>';
        $data .= $this->language->get('ginger_residence'). $bankInformation['ginger_residence']. '<br>';

        return $data;
    }
}