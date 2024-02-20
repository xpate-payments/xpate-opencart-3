<?php
namespace components;

use Ginger\Ginger;

class GingerClientBuilder
{

    /**
     *  Function returns path to certificate
     */
    public static function getCaCertPath()
    {
        return DIR_SYSTEM.'library/ginger/assets/cacert.pem';
    }

    public static function getClient($apiKey, $useBundle)
    {
        return Ginger::createClient(
            GingerBankConfig::BANK_ENDPOINT,
            $apiKey,
            $useBundle ?
                [
                    CURLOPT_CAINFO => self::getCaCertPath()
                ] : []
        );
    }

}