<?php
namespace components;

use interfaces\GingerIssuers;
use interfaces\GingerOrderLines;
use interfaces\GingerTermsAndConditions;

class GingerOrderBuilder
{

    private $orderInfo;
    private $paymentMethodObj;

    public function __construct(array $orderInfo, $paymentMethod)
    {
        $this->orderInfo = $orderInfo;
        $this->paymentMethodObj = $paymentMethod;
    }

    /**
     * Generate an order.
     *
     * @param array
     * @return array
     */
    public function getBuiltOrder()
    {
        $order = [];

        $order['amount'] = $this->getAmountInCents();
        $order['currency'] = $this->getOrderCurrency();
        $order['description'] = $this->getOrderDescription();
        $order['merchant_order_id'] = $this->getMerchantOrderID();
        $order['return_url'] = $this->getReturnURL();
        $order['customer'] = $this->getCustomerInformation();
        $order['extra'] = $this->getExtra();
        $order['webhook_url'] = $this->getWebhookURL();
        $order['order_lines'] = $this->getOrderLines($this->getAmountInCents());
        $order['transactions'][] = $this->getOrderTransactions();

        return $order;
    }

    /**
     * @return string
     */
    public function getSelectedIssuer(): string
    {
        return array_key_exists('issuer_id', $this->paymentMethodObj->request->post) ? $this->paymentMethodObj->request->post['issuer_id'] : "";
    }

    public function getOrderTransactions(): array
    {
        return array_filter([
            'payment_method' => $this->getPaymentMethod(),
            'payment_method_details' => $this->getPaymentMethodDetails()
        ]);
    }

    /**
     * @return array|string[]
     * @throws Exception
     */
    public function getPaymentMethodDetails(): array
    {
        $paymentMethodDetails = [];

        //uses for ideal
        if ($this->paymentMethodObj instanceof GingerIssuers)
        {
            $paymentMethodDetails['issuer_id'] = $this->getSelectedIssuer();
            return $paymentMethodDetails;
        }

        //uses for afterpay
        if ($this->paymentMethodObj instanceof GingerTermsAndConditions)
        {

            $termsAndConditionFlag = array_key_exists('ap_terms_and_conditions', $this->paymentMethodObj->request->post)
                                    ? $this->paymentMethodObj->request->post['ap_terms_and_conditions']
                                    : "";
            if ($termsAndConditionFlag)
            {
                $paymentMethodDetails = [
                    'verified_terms_of_service' => true,
                ];
            }
            return $paymentMethodDetails;

        }

        return $paymentMethodDetails;

    }


    /**
     * @return string
     */
    public function getPaymentMethod(): string
    {
        return GingerBankConfig::gingerPaymentNameMapping($this->paymentMethodObj->paymentName);
    }


    /**
     * @return array
     */
    public function getExtra(): array
    {
        return [
            'user_agent' => $this->getUserAgent(),
            'platform_name' => $this->getPlatformName(),
            'platform_version' => $this->getPlatformVersion(),
            'plugin_name' => $this->getPluginName(),
            'plugin_version' => $this->getPluginVersion()
        ];
    }


    /**
     * @return string
     */
    public function getReturnURL(): string
    {
        return $this->paymentMethodObj->url->link('extension/payment/'.str_replace(GingerBankConfig::BANK_PREFIX,'ginger',$this->paymentMethodObj->paymentName).'/callback');
    }

    /**
     * @return string
     */
    public function getMerchantOrderID(): string
    {
        return $this->orderInfo['order_id'];
    }


    /**
     * @param $full_amount
     * @return int
     */
    public function getAmountInCents($full_amount = ""): int
    {
        $total = $full_amount ?: $this->orderInfo['total'];
        $amount = $this->paymentMethodObj->currency->format(
            $total,
            $this->orderInfo['currency_code'],
            $this->orderInfo['currency_value'],
            false
        );

        return round($amount * 100);
    }

    /**
     * @param $totalAmountInCents
     * @return array
     */
    public function getOrderLines($totalAmountInCents): array
    {
        $orderLinesTotalAmountInCents = 0;
        $this->paymentMethodObj->load->model('tool/image');
        $orderLines = [];

        foreach ($this->paymentMethodObj->cart->getProducts() as $item)
        {
            $amount = $this->getAmountInCents($this->paymentMethodObj->tax->calculate($item['price'], $item['tax_class_id'], true));
            $orderLines[] = array_filter([
                'url' =>  $this->paymentMethodObj->url->link('product/product', 'product_id='.$item['product_id']),
                'name' => $item['name'],
                'type' => 'physical',
                'amount' => $amount,
                'currency' => $this->orderInfo['currency_code'],
                'quantity' => (int) $item['quantity'],
                'image_url' =>  $this->paymentMethodObj->model_tool_image->resize($item['image'], 100, 100),
                'vat_percentage' => $this->getOrderLineTaxRate($item['price'], $item['tax_class_id']), 'merchant_order_line_id' => (string) $item['product_id']],
                function($value) {
                    return !is_null($value);
                });
            $orderLinesTotalAmountInCents += $amount * $item['quantity'];
        }

        if (array_key_exists('shipping_method',  $this->paymentMethodObj->session->data)
            && intval( $this->paymentMethodObj->session->data['shipping_method']['cost']) > 0)
        {
            $shipping_costs = $this->getShippingOrderLine();
            $orderLines[] = $shipping_costs;
            $orderLinesTotalAmountInCents += $shipping_costs['amount'];
        }

        if (($totalAmountInCents - $orderLinesTotalAmountInCents) != 0)
        {
            $orderLines[] = [
                'name' => 'Overig',
                'type' => 'physical',
                'amount' => $totalAmountInCents - $orderLinesTotalAmountInCents,
                'currency' => $this->orderInfo['currency_code'],
                'quantity' => 1,
                'vat_percentage' => 2100,
                'merchant_order_line_id' => 'miscellaneous',
            ];
        }
        return $orderLines;
    }


    /**
     * @return array
     */
    public function getShippingOrderLine()
    {
        $shippingMethod = $this->paymentMethodObj->session->data['shipping_method'];

        return [
            'name' => $shippingMethod['title'],
            'type' => 'shipping_fee',
            'amount' => $this->getAmountInCents($this->paymentMethodObj->tax->calculate($shippingMethod['cost'], $shippingMethod['tax_class_id'], true)),
            'currency' => $this->orderInfo['currency_code'],
            'vat_percentage' => $this->getOrderLineTaxRate($shippingMethod['cost'],$shippingMethod['tax_class_id']),
            'quantity' => 1,
            'merchant_order_line_id' => (string) (count($this->paymentMethodObj->cart->getProducts()) + 1)
        ];
    }



    /**
     * @return string
     */
    public function getPluginVersion(): string
    {
        return $this->paymentMethodObj::PLUGIN_VERSION;
    }

    /**
     * @param $price
     * @param $taxClassId
     * @return int|null
     */
    public function getOrderLineTaxRate($price, $taxClassId)
    {
        $taxRate = 0;
        $appliedTaxRates = $this->paymentMethodObj->tax->getRates($price, $taxClassId);

        if (count($appliedTaxRates) > 0)
        {
            foreach ($appliedTaxRates as $appliedTaxRate) $taxRate += $appliedTaxRate['rate'];
        }

        return (int) round($taxRate * 100);
    }

    /**
     * @return string
     */
    public function getOrderCurrency(): string
    {
        return $this->orderInfo['currency_code'];
    }

    /**
     * @return string
     */
    public function getWebhookURL(): string
    {
        return $this->paymentMethodObj->url->link('extension/payment/'.str_replace(GingerBankConfig::BANK_PREFIX,'ginger',$this->paymentMethodObj->paymentName).'/webhook');
    }

    /**
     * @param string $locale
     * @return mixed
     */
    public function formatLocale($locale)
    {
        return strstr($locale, '-', true);
    }

    /**
     * @return string
     */
    public function getOrderDescription(): string
    {
        $this->paymentMethodObj->language->load('extension/payment/ginger_common');
        return sprintf($this->paymentMethodObj->language->get('text_your_order_at'), $this->orderInfo['order_id'], $this->paymentMethodObj->config->get('config_name'));
    }

    /**
     * @return array
     */
    public function getCustomerInformation(): array
    {
        return array_filter([
            'address_type' => 'customer',
            'country' => $this->getCountry(),
            'email_address' => $this->getEmail(),
            'first_name' => $this->getFirstName(),
            'last_name' => $this->getLastName(),
            'merchant_customer_id' => $this->getMerchantCustomerID(),
            'phone_numbers' => $this->getPhoneNumbers(),
            'address' => implode("\n", array_filter(array(
                $this->getFirstShippingAddress(),
                $this->getSecondShippingAddress(),
                $this->getShippingPostCode()." ".$this->getShippingCity()
            ))),
            'locale' => $this->getLocale(),
            'ip_address' => $this->getIPAddress(),
            'gender' => $this->getGender(),
            'birthdate' => $this->getBirthdayDate(),
            'additional_addresses' => $this->getAdditionalAddresses()
        ]);
    }

    public function getGender()
    {
        return filter_input(INPUT_POST,'gender',FILTER_SANITIZE_STRING);
    }

    public function getBirthdayDate()
    {
        $birthday = filter_input(INPUT_POST,'dob',FILTER_SANITIZE_STRING);
        return $birthday ? date("Y-m-d", strtotime($birthday)) : null;
    }

    public function getAdditionalAddresses()
    {
        return [
            [
                'address_type' => 'billing',
                'address' => implode("\n", array_filter(array(
                    $this->getFirstPaymentAddress(),
                    $this->getSecondPaymentAddress(),
                    $this->getPaymentPostCode()." ".$this->getPaymentCity()
                ))),
                'country' => $this->getCountry(),
            ],
        ];
    }

    public function getFirstPaymentAddress()
    {
        return $this->orderInfo['payment_address_1'];
    }


    public function getSecondPaymentAddress()
    {
        return $this->orderInfo['payment_address_2'];
    }

    public function getPaymentPostCode()
    {
        return $this->orderInfo['payment_postcode'];
    }


    public function getPaymentCity()
    {
        return $this->orderInfo['payment_city'];
    }

    public function getFirstShippingAddress()
    {
        return $this->orderInfo['shipping_address_1'];
    }


    public function getSecondShippingAddress()
    {
        return $this->orderInfo['shipping_address_2'];
    }

    public function getShippingPostCode()
    {
        return $this->orderInfo['shipping_postcode'];
    }


    public function getShippingCity()
    {
        return $this->orderInfo['shipping_city'];
    }


    public function getEmail()
    {
        return $this->orderInfo['email'];
    }

    public function getCountry()
    {
        return$this->orderInfo['payment_iso_code_2'];
    }


    public function getFirstName()
    {
        return $this->orderInfo['payment_firstname'];
    }


    public function getLastName()
    {
        return $this->orderInfo['payment_lastname'];
    }


    public function getLocale()
    {
        return $this->formatLocale($this->orderInfo['language_code']);
    }



    public function getIPAddress()
    {
        return filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP);
    }



    public function getPhoneNumbers()
    {
        return [
            $this->orderInfo['telephone']
        ];
    }

    public function getMerchantCustomerID()
    {
        return $this->orderInfo['customer_id'];
    }

    public function getUserAgent()
    {
        return $_SERVER['HTTP_USER_AGENT'];
    }

    public function getPluginName()
    {
        return GingerBankConfig::PLUGIN_NAME;
    }
    public function getPlatformName()
    {
        return 'OpenCart3';
    }

    public function getPlatformVersion()
    {
        return VERSION;

    }


}