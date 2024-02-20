<?php


namespace components;


trait MultiCurrencyCaching
{

    public function getAllowedCurrency()
    {
        if (file_exists(__DIR__."/../ginger_currency_list.json"))
        {
            $currencyList = json_decode(file_get_contents(__DIR__."/../ginger_currency_list.json"),true);
            if ($currencyList['expired_time'] > time()) return $currencyList['currency_list'];
        }

        $allowed_currencies = $this->cacheCurrencyList();

        return $allowed_currencies;
    }


    public function cacheCurrencyList()
    {
        $allowed_currencies = $this->gingerClient->getCurrencyList();
        $currencyListWithExpiredTime = [
            'currency_list' => $allowed_currencies,
            'expired_time' => time() + (60*6)
        ];
        file_put_contents(__DIR__."/../ginger_currency_list.json", json_encode($currencyListWithExpiredTime));

        return $allowed_currencies;
    }

}