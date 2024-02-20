<?php
require_once(DIR_SYSTEM.'library/ginger/autoload.php');
use banktwins\GingerBankAdminController;

class ControllerExtensionPaymentGinger extends GingerBankAdminController
{
    public $paymentName = 'ginger';
}
