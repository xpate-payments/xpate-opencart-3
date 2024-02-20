<?php
require_once(DIR_SYSTEM.'library/ginger/autoload.php');

use banktwins\GingerBankModel;

class ModelExtensionPaymentGingerCreditCard extends GingerBankModel
{
    protected $paymentName = 'ginger_creditcard';
}
