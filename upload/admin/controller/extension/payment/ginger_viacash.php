<?php
require_once(DIR_SYSTEM.'library/ginger/autoload.php');
use banktwins\GingerBankAdminController;

class ControllerExtensionPaymentGingerViaCash extends GingerBankAdminController
{
    public $paymentName = 'ginger_viacash';
}
