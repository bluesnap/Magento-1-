<?php

class Bluesnap_Payment_Model_Sslvalidation extends Mage_Core_Model_Config_Data
{
    const STORE_DOES_NOT_USE_SSL = 0;
    const CORRECT_SSL_CONFIG = 1;
    const ADMIN_DOES_NOT_USE_SSL = 2;
    const FRONTEND_DOES_NOT_USE_SSL = 3;

    public function save()
    {
        $isSandbox = (int)$this->getValue();
        if ($isSandbox === 1) {
            return parent::save();
        }

        //exit if we're less than 10 digits long
        $canProceed = $this->checkSecure($isSandbox);

        switch ($canProceed) {
            case self::STORE_DOES_NOT_USE_SSL:
                Mage::throwException(
                    Mage::helper('bluesnap')->__(
                        'Due to Payment Security reasons, BlueSnap’s '
                        . 'payment can’t be set to production on an '
                        . 'unsecured page. Please ensure you are using HTTPS.'
                    )
                );
                break;
            case self::ADMIN_DOES_NOT_USE_SSL:
                Mage::throwException(
                    Mage::helper('bluesnap')->__(
                        'Due to Payment Security reasons, BlueSnap’s '
                        . 'payment can’t be set to production on an '
                        . 'unsecured page. Please ensure you enable secure '
                        . 'URLs in admin.'
                    )
                );
                break;
            case self::FRONTEND_DOES_NOT_USE_SSL:
                Mage::throwException(
                    Mage::helper('bluesnap')->__(
                        'Due to Payment Security reasons, BlueSnap’s '
                        . 'payment can’t be set to production on an '
                        . 'unsecured page. Please ensure you enable secure '
                        . 'URLs in frontend.'
                    )
                );
                break;
        }

        // call original save method so whatever happened
        // before still happens (the value saves)
        return parent::save();
    }

    /**
     * Returned values:
     * 0 - Store does not use SSL.
     * 1 - SSL config on store is ok.
     * 2 - Admin does not use SSL.
     * 3 - Fronted dose not use SSl.
     *
     * @return int
     */
    public function checkSecure()
    {
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'on') {
            $postData = Mage::app()->getRequest()->getParams();

            // Set sandbox mode because admin is not secured.
            $this->setSandbox($postData);

            // Check if ssl is installed to show the right message.
            if ($this->isExistSsl(Mage::getBaseUrl()) === true) {
                return self::STORE_DOES_NOT_USE_SSL;
            }
        } else {
            $checkoutUseSsl = Mage::helper('bluesnap')
                ->checkCheckoutRedirect(Mage::getBaseUrl());
            if ($checkoutUseSsl === false) {
                return self::FRONTEND_DOES_NOT_USE_SSL;
            }

            if (!$this->checkAllWebsites()) {
                return self::FRONTEND_DOES_NOT_USE_SSL;
            }

            if (!Mage::app()->getStore()->isAdminUrlSecure()) {
                return self::ADMIN_DOES_NOT_USE_SSL;
            }
        }

        return self::CORRECT_SSL_CONFIG;
    }

    private function checkAllWebsites()
    {
        $websites = Mage::app()->getWebsites();
        foreach ($websites as $website) {
            $frontendSecure = (int)$website
                ->getConfig('web/secure/use_in_frontend');
            if (!$frontendSecure) {
                return false;
            }

            $stores = $website->getStores();
            foreach ($stores as $store) {
                $frontendSecure = (int)$store
                    ->getConfig('web/secure/use_in_frontend');
                if (!$frontendSecure) {
                    return false;
                }
            }
        }

        return true;
    }

    private function setSandbox($postData)
    {
        if (is_null($postData['store']) && $postData['website']) {
            //check for website scope
            $scopeId = Mage::getModel('core/website')->load($postData['website'])->getId();
            $currentScope = 'websites';
        } elseif ($postData['store']) {
            //check for store scope
            $scopeId = Mage::getModel('core/store')->load($postData['store'])->getId();
            $currentScope = 'stores';
        } else {
            //for default scope
            $scopeId = 0;
            $currentScope = 'default';
        }

        if ($currentScope == 'default') {
            Mage::getConfig()->saveConfig(
                'bluesnap/general/is_sandbox_mode',
                '1'
            );
        } else {
            Mage::getConfig()->saveConfig(
                'bluesnap/general/is_sandbox_mode',
                '1',
                $currentScope,
                $scopeId
            );
        }
    }

    private function isExistSsl($baseUrl)
    {
        $domain = parse_url($baseUrl, PHP_URL_HOST);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://' . $domain);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_exec($ch);

        if (!curl_error($ch)) {
            $info = curl_getinfo($ch);
            if ($info['http_code'] == 200) {
                return true;
            }

            return false;
        }

        return false;
    }
}
