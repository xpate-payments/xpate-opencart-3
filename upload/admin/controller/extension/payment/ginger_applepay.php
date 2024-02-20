<?php
require_once(DIR_SYSTEM.'library/ginger/autoload.php');
use banktwins\GingerBankAdminController;

class ControllerExtensionPaymentGingerApplePay extends GingerBankAdminController
{
    public $paymentName = 'ginger_applepay';
}
