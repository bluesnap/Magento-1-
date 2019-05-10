<?php

/**
 * Utils for formatting requests
 * Class Bluesnap_Payment_Helper_Data
 */
class Bluesnap_Payment_Helper_Data extends Bluesnap_Payment_Helper_Config
{
    /**
     * Convert HTTP request object to string.
     * Used to log IPN requests
     * @param Mage_Core_Controller_Request_Http $request
     * @return string
     */
    public function requestToString(Mage_Core_Controller_Request_Http $request)
    {
        $string = 'From ' . $request->getServer('REMOTE_ADDR') . ":\n";
        foreach ($request->getParams() as $key => $value) {
            $string .= "{$key}: {$value}\n";
        }
        return $string;
    }

    /**
     * get language name for current locale
     * @return string
     */
    public function getLangByLocale()
    {
        $locale = Mage::app()->getStore()->getLocaleCode();
        if (!isset($this->locales[$locale])) {
            $locale = 'en_US';
        }

        return $this->locales[$locale];
    }

    /**
     * Convert array to xml object
     * @param array $arr
     * @param SimpleXMLElement $xml
     * @return SimpleXMLElement
     */
    public function arrayToXml(array $arr, SimpleXMLElement $xml)
    {
        foreach ($arr as $k => $v) {
            is_array($v)
                ? $this->arrayToXml($v, $xml->addChild($k))
                : $xml->addChild($k, $v);
        }
        return $xml;
    }

    /**
     * Format User Agent for the API request
     * @return string
     */
    public function getUserAgent()
    {
        return sprintf('BlueSnap Magento API %s/%s', Mage::getVersion(), $this->getVersion());
    }

    /**
     * Get module version
     * @return string
     */
    public function getVersion()
    {
        return (string)Mage::getConfig()->getModuleConfig('Bluesnap_Payment')->version;
    }

    public function isStateSupported($state)
    {
        $notSuppStates = array('FM', 'AE', 'MH', 'MP', 'AA', 'AP', 'PW', 'AF', 'AM');

        if (in_array($state, $notSuppStates)) {
            return false;
        } else {
            return true;
        }
    }

    public function isCountrySupported($country)
    {
        $notSuppCountries = array('IQ', 'SY', 'KP', 'MM', 'LY', 'LB', 'YE', 'IR', 'CU', 'SD', 'AF');

        if (in_array($country, $notSuppCountries)) {
            return false;
        } else {
            return true;
        }
    }

    public function getErroMessage($error_code)
    {
        switch ($error_code) {
            case 15003:
                $message = 'The currency selected is not supported by this '
                    . 'payment method. Please try a different currency or '
                    . 'payment method.';
                break;
            case 14002:
                $message = 'Your payment could not be processed at this time. '
                    . 'Please make sure the card information was entered '
                    . 'correctly and resubmit. If the problem persists, '
                    . 'please contact your credit card company to authorize '
                    . 'the purchase.';
                break;
            default:
                $message = 'Unfortunately an error has occurred and your '
                    . 'payment cannot be processed at this time, please '
                    . 'verify your payment details or try again later. '
                    . 'If the problem persists, please contact our support team.';
        }

        return $message;
    }

    /**
     * Method check that checkout is always run with ssl.
     *
     * @param string $baseUrl
     *
     * @return bool
     */
    public function checkCheckoutRedirect($baseUrl)
    {
        $domain = parse_url($baseUrl, PHP_URL_HOST);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://' . $domain . '/checkout');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_exec($ch);

        $error = curl_error($ch);
        if ($error) {
            return false;
        }

        $info = curl_getinfo($ch);
        if (isset($info['redirect_url'])
            && strpos($info['redirect_url'], 'https') !== false
        ) {
            return true;
        }

        return false;
    }
}
