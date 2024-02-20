<?php
require_once(DIR_SYSTEM.'library/ginger/autoload.php');

use banktwins\GingerBankModel;

class ModelExtensionPaymentGinger extends GingerBankModel
{
    protected $paymentName = 'ginger';
}
