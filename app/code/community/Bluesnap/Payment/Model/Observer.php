<?php

class Bluesnap_Payment_Model_Observer extends Mage_Core_Model_Session_Abstract
{
    /**
     * put currency rate to order
     *
     * @param mixed $observer
     */
    function salesOrderSaveBefore($observer)
    {

        $order = $observer->getOrder();

        $amount = $order->getGrandTotal();
        $orderCurrencyCode = $order->getOrderCurrencyCode();

        $baseAmount = $order->getBaseGrandTotal();
        $baseCurrencyCode = $order->getBaseCurrencyCode();
        $orderCurrencyCode = $order->getOrderCurrencyCode();
        $currency = Mage::getModel('directory/currency');
        $currency->load($baseCurrencyCode);
        //local supported
        if (!$currency->isBluesnapCurrencySupported($orderCurrencyCode)) {
            $bluesnapCurrencyCode = $baseCurrencyCode;
            $bluesnapCurrencyRate = 1;
            $bluesnapCurrencyAmount = $baseAmount;
        } else {
            //all other currencies
            $baseCurrency = Mage::getModel('directory/currency');
            $baseCurrency->load($order->getBaseCurrencyCode());

            $bluesnapCurrencyCode = Bluesnap_Payment_Helper_Config::BLUESNAP_DEFAULT_CURRENCY;
            $bluesnapCurrencyRate = $baseCurrency->getBluesnapRate(
                $orderCurrencyCode,
                $baseCurrencyCode
            );

            if ($order->getBaseCurrencyCode() == 'USD') {
                //do not convert
                $bluesnapCurrencyAmount = $order->getBaseGrandTotal();
            } else {
                $bluesnapCurrencyAmount = $baseCurrency->convert(
                    $order->getBaseGrandTotal(),
                    $bluesnapCurrencyCode
                );
            }
        }

        $order->setBluesnapCurrencyRate($bluesnapCurrencyRate);
        $order->setBluesnapCurrencyCode($baseCurrencyCode);
        $order->setBluesnapGrandTotal($baseAmount);
    }

    /**
     * put currency rate to quote
     *
     * @param mixed $observer
     */
    public function salesQuoteSaveBefore($observer)
    {
        $quote = $observer->getQuote();

        $amount = $quote->getGrandTotal();
        $quoteCurrencyCode = $quote->getQuoteCurrencyCode();

        $baseAmount = $quote->getBaseGrandTotal();
        $baseCurrencyCode = $quote->getBaseCurrencyCode();

        $currency = Mage::getModel('directory/currency');

        $currency->load($baseCurrencyCode);

        //local supported
        if ($currency->isBluesnapCurrencySupported($baseCurrencyCode)) {
            $bluesnapCurrencyCode = $baseCurrencyCode;
            $bluesnapCurrencyRate = 1;
            $bluesnapCurrencyAmount = $baseAmount;
            //exists, not supported (rate of bsnap) //all other currencies (rate of webservicex)
        } else {
            //all other currencies
            $baseCurrency = Mage::getModel('directory/currency');
            $baseCurrency->load($quote->getBaseCurrencyCode());

            $bluesnapCurrencyCode = Bluesnap_Payment_Helper_Config::BLUESNAP_DEFAULT_CURRENCY;

            $bluesnapCurrencyRate = $baseCurrency->getRate($bluesnapCurrencyCode);

            if ($quote->getBaseCurrencyCode() == 'USD') {
                //do not convert
                $bluesnapCurrencyAmount = $quote->getBaseGrandTotal();
            } else {
                $bluesnapCurrencyAmount = $baseCurrency->convert(
                    $quote->getBaseGrandTotal(),
                    $bluesnapCurrencyCode
                );
            }
        }

        $bluesnapCurrencyAmount = Mage::app()->getStore()->roundPrice($bluesnapCurrencyAmount);

        $quote->setBluesnapCurrencyRate($bluesnapCurrencyRate);
        $quote->setBluesnapCurrencyCode($bluesnapCurrencyCode);
        $quote->setBluesnapGrandTotal($bluesnapCurrencyAmount);
    }

    public function salesOrderGridCollectionLoadBefore($observer)
    {
        $collection = $observer->getOrderGridCollection();
        $collection->addFilterToMap('store_id', 'main_table.store_id');
        $select = $collection->getSelect();
        $select->joinLeft(
            array('oe' => $collection->getTable('sales/order')),
            'oe.entity_id=main_table.entity_id',
            array(
                'oe.bluesnap_reference_number',
                'oe.bluesnap_grand_total',
                'oe.bluesnap_total_paid',
                'oe.bluesnap_currency_code',
            )
        );
    }

    public function appendCustomColumn(Varien_Event_Observer $observer)
    {
        $block = $observer->getBlock();
        if (!isset($block)) {
            return $this;
        }

        if ($block->getType() == 'adminhtml/sales_order_grid') {
            /* @var $block Mage_Adminhtml_Block_Customer_Grid */
            $this->_addColumnToGrid($block);
        }
    }

    protected function _addColumnToGrid($grid)
    {
        /* @var $block Mage_Adminhtml_Block_Customer_Grid */
        $grid->addColumnAfter('bluesnap_reference_number', array(
            'header' => Mage::helper('bluesnap')->__('Bluesnap Reference Number'),
            'index' => 'bluesnap_reference_number',
            'filter_index' => 'oe.bluesnap_reference_number',
            'type' => 'text',
            //'options' => $groups,
        ), 'increment_id');

        $grid->addColumnAfter('bluesnap_grand_total', array(
            'header' => Mage::helper('bluesnap')->__('Bluesnap Grand Total'),
            'index' => 'bluesnap_grand_total',
            'filter_index' => 'oe.bluesnap_grand_total',
            'type' => 'currency',
            'currency' => 'bluesnap_currency_code',
            //'options' => $groups,
        ), 'grand_total');

        $grid->addColumnAfter('bluesnap_total_paid', array(
            'header' => Mage::helper('bluesnap')->__('Bluesnap Total Paid'),
            'index' => 'bluesnap_total_paid',
            'filter_index' => 'oe.bluesnap_total_paid',
            'type' => 'currency',
            'currency' => 'bluesnap_currency_code',
            //'options' => $groups,
        ), 'bluesnap_grand_total');
    }

    public function salesOrderCreditmemoRefund($observer)
    {
        $creditmemo = $observer->getCreditmemo();
        $order = $creditmemo->getOrder();
        $payment = $creditmemo->getOrder()->getPayment();

        $baseTotalRefunded = $order->getBaseTotalRefunded();
        //get bluesnap refund by percentage
        $bluesnapTotalRefunded = $baseTotalRefunded / $order->getBaseGrandTotal() * $order->getBluesnapGrandTotal();
        $bluesnapTotalRefunded = round($bluesnapTotalRefunded, 2);

        //$creditmemo->setTransactionId($payment->getLastTransId());
        $creditmemo->setBluesnapCurrencyCode($payment->getOrder()->getBluesnapCurrencyCode());
        $creditmemo->setBluesnapGrandTotal($bluesnapTotalRefunded);

        return $this;
    }

    public function salesOrderInvoiceRegister($observer)
    {
        $invoice = $observer->getInvoice();
        $order = $observer->getOrder();

        //$order->getBaseTotalInvoiced()
        //@todo - move it to place_end event
        $invoice->setBluesnapGrandTotal($order->getBluesnapGrandTotal());
        $invoice->setBluesnapCurrencyCode($order->getBluesnapCurrencyCode());

        return $this;
    }


    public function sendCron()
    {
        $days = Mage::getStoreConfig('bluesnap/logger/collect_data_for');

        $collection = Mage::getResourceModel('bluesnap/logger_db_entry_collection')
            ->addFieldToFilter('created_at', array(
                'lt' => date("Y-m-d H:i:s", strtotime('-' . $days . 'day')),
            ));
        foreach ($collection as $logger) {
            $logger->delete();
        }

        $collection->save();
    }

    public function addJsOnestep($observer)
    {
        $controller = $observer->getAction();
        $controllerName = $controller->getRequest()->getRouteName();
        $exist = strpos($controllerName, 'onestepcheckout');
        if ($exist !== false) {
            $layout = $controller->getLayout();
            //if( $layout->getBlock('head')){
            $layout->getBlock('head')->addLinkRel(
                'text/javascript',
                'https://gateway.bluesnap.com/js/cse/v1.0.2/bluesnap.js'
            );
            $layout->getBlock('head')->addItem(
                'js',
                'bluesnap/credit-card-detect.js'
            );
            $layout->getBlock('head')->addItem('js', 'bluesnap/payform.js');
            $layout->getBlock('head')->addItem('js', 'bluesnap/bsadmin.js');
            $layout->getBlock('head')->addItem(
                'js',
                'lib/jquery/jquery-ui/jquery-ui.js'
            );
            $layout->getBlock('head')->addItem(
                'js',
                'lib/jquery/jquery-ui/jquery-ui.js'
            );
            $layout->getBlock('head')->addItem(
                'skin_css',
                'css/bluesnap/buynow/checkout.css'
            );
            $layout->getBlock('head')->addItem(
                'js_css',
                'lib/jquery/jquery-ui/jquery-ui.css'
            );
            //}
        }
    }

    /**
     * Validate if secure urls are enabled on frontend and admin.
     * If they are not, bluesnap can not be set to production mode.
     *
     * @param Varien_Event_Observer $observer Observer object.
     *
     * @return Bluesnap_Payment_Model_Observer
     * @throws Mage_Core_Exception
     */
    public function validateSslConfiguration(Varien_Event_Observer $observer)
    {
        $sandboxMode = (int)Mage::getStoreConfig('bluesnap/general/is_sandbox_mode');
        if ($sandboxMode === 1) {
            return $this;
        }

        $fallback = false;
        $websites = Mage::app()->getWebsites();

        foreach ($websites as $website) {
            $frontendSecure = (int)$website->getConfig('web/secure/use_in_frontend');
            if (!$frontendSecure) {
                $fallback = true;
                break;
            }

            $stores = $website->getStores();
            foreach ($stores as $store) {
                $frontendSecure = (int)$store->getConfig('web/secure/use_in_frontend');
                if (!$frontendSecure) {
                    $fallback = true;
                    break;
                }
            }
        }

        $checkoutSecure = Mage::helper('bluesnap')
            ->checkCheckoutRedirect(Mage::getBaseUrl());
        $adminSecure = Mage::app()->getStore()->isAdminUrlSecure();

        if (!$checkoutSecure || !$adminSecure) {
            $fallback = true;
        }

        if ($fallback === true) {
            Mage::getModel('core/config')->saveConfig(
                'bluesnap/general/is_sandbox_mode',
                1
            );
            Mage::throwException(
                Mage::helper('bluesnap')->__(
                    'Bluesnap sandbox mode has been enabled. '
                    . 'Production mode can not works without SSL.'
                )
            );
        }

        return $this;
    }
}
