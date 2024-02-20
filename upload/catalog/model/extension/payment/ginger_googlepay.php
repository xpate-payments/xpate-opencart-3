<?php
require_once(DIR_SYSTEM.'library/ginger/autoload.php');

use banktwins\GingerBankModel;

class ModelExtensionPaymentGingerGooglePay extends GingerBankModel
{
    protected $paymentName = 'ginger_googlepay';
}
