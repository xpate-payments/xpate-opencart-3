<?php
namespace components;

class GingerBankConfig
{
    const BANK_PREFIX = "xpate";
    const BANK_LABEL = "Xpate";
    const PLUGIN_NAME = "xpate-online-opencart-3";

    const BANK_ENDPOINT = 'https://api.online.emspay.eu';


    public static function gingerPaymentNameMapping($paymentMethodName)
    {
        $paymentMap = [
            'xpate_klarnapaynow' => 'klarna-pay-now',
            'xpate_afterpay' => 'afterpay',
            'xpate_amex' => 'amex',
            'xpate_applepay' => 'apple-pay',
            'xpate_googlepay' => 'google-pay',
            'xpate_bancontact' => 'bancontact',
            'xpate_sofort' => 'sofort',
            'xpate_banktransfer' => 'bank-transfer',
            'xpate_creditcard' => 'credit-card',
            'xpate_ideal' => 'ideal',
            'xpate_klarnapaylater' => 'klarna-pay-later',
            'xpate_klarnadirectdebit' => 'klarna-direct-debit',
            'xpate_payconiq' => 'payconiq',
            'xpate_paypal' => 'paypal',
            'xpate_swish' => 'swish',
            'xpate_giropay' => 'giropay',
            'xpate_mobilepay' => 'mobilepay',
            'xpate_viacash' => 'viacash'
        ];

        return $paymentMap[$paymentMethodName];
    }


}