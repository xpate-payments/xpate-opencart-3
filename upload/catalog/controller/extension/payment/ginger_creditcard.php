<?php
require_once(DIR_SYSTEM.'library/ginger/autoload.php');

use banktwins\GingerBankPluginController;
use components\GingerBankConfig;

class ControllerExtensionPaymentGingerCreditCard extends GingerBankPluginController
{
    /**
     * Payments module name
     */
    public $paymentName = GingerBankConfig::BANK_PREFIX.'_creditcard';
}
