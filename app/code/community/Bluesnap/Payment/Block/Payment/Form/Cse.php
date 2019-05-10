<?php

/**
 * Prepare form for the CSE method
 * Class Bluesnap_Payment_Block_Payment_Form_Cse
 */
class Bluesnap_Payment_Block_Payment_Form_Cse extends Mage_Payment_Block_Form_Cc
{
    /**
     * Get notice message
     * @param $message
     * @return string
     */
    public function showNoticeMessage($message)
    {
        return $this->getLayout()->getMessagesBlock()
            ->addNotice($this->__($message))
            ->getGroupedHtml();
    }

    /**
     * Get merchant's public key
     * @return string
     */
    public function getPublicKey()
    {
        return $this->getMethod()->getPublicKey();
    }

    /**
     * Get list of saved cards
     * @return array
     */
    public function getCards()
    {
        $result = array();

        if ($cards = Mage::registry('bs_shopper_cards')) {

            foreach ($cards as $card => $type) {
                $cc_data = $card . '/' . $type;
                $result[md5($card)] = array(
                    'enc_type' => Mage::helper('core')->encrypt($cc_data),
                    'type' => $type,
                    'card' => $card
                );
            }
        }

        return $result;
    }

    /**
     * Set block template
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('bluesnap/payment/form/cse.phtml');
    }

    /**
     * Check status sandbox mode.
     *
     * @return bool
     */
    protected function checkSandboxMode() {
        return (bool) Mage::getStoreConfig('bluesnap/general/is_sandbox_mode');
    }

    /**
     * Get store id.
     *
     * @return int
     */
    protected function getStoreId() {
        return (int) Mage::app()->getStore()->getStoreId();
    }

    /**
     * Get merchant id.
     *
     * @return bool
     */
    protected function getMerchantId() {
        return (bool) Mage::getStoreConfig('bluesnap/general/merchant_id');
    }

    /**
     * Get checkout session id.
     *
     * @return int
     */
    protected function getCheckoutSessionId() {
        return (int) Mage::getSingleton('checkout/session')->getSessionId();
    }

    /**
     * Create url query string.
     *
     * @return string
     */
    protected function createQueryUrlString() {
        $storeId = $this->getStoreId();
        $checkoutSessionId = $this->getCheckoutSessionId();
        $urlQuery = '?d='.$storeId.'&s='.$checkoutSessionId;

        return $urlQuery;
    }

    /**
     * Get iframe src.
     *
     * @return string
     */
    protected function getIframeSrc() {
        $sandboxMode = $this->checkSandboxMode();
        $urlQuery = $this->createQueryUrlString();

        if($sandboxMode === true) {
            $domain = 'https://sandbox.bluesnap.com';
        } else {
            $domain = 'https://bluesnap.com';
        }

        $url = $domain.'/servlet/logo.htm'.$urlQuery;

        return $url;
    }


    /**
     * Get img src.
     *
     * @return string
     */
    protected function getImgSrc() {
        $sandboxMode = $this->checkSandboxMode();
        $urlQuery = $this->createQueryUrlString();

        if($sandboxMode === true) {
            $domain = 'https://sandbox.bluesnap.com';
        } else {
            $domain = 'https://bluesnap.com';
        }

        $url = $domain.'/logo.gif'.$urlQuery;

        return $url;
    }

    /**
     * Render block HTML
     * @return string
     */
    protected function _toHtml()
    {
     $this->setChild('cards', $this->getCardsBlock());
        $this->setChild('method_form_block', $this->getMethodFormBlock());
          if( $this->getLayout()->getBlock('head')){
        $this->getLayout()->getBlock('head')->addLinkRel('text/javascript','https://gateway.bluesnap.com/js/cse/v1.0.2/bluesnap.js');
 	        $this->getLayout()->getBlock('head')->addItem('js','bluesnap/credit-card-detect.js');
 			$this->getLayout()->getBlock('head')->addItem('js','bluesnap/payform.js');
 			$this->getLayout()->getBlock('head')->addItem('js','bluesnap/bsadmin.js');		
 			$this->getLayout()->getBlock('head')->addItem('js','lib/jquery/jquery-ui/jquery-ui.js');
 			$this->getLayout()->getBlock('head')->addItem('js','lib/jquery/jquery-ui/jquery-ui.js');
 			$this->getLayout()->getBlock('head')->addItem('skin_css', 'css/bluesnap/buynow/checkout.css');
 			$this->getLayout()->getBlock('head')->addItem('js_css', 'lib/jquery/jquery-ui/jquery-ui.css');
 		}

        return parent::_toHtml();
    }
	
	 protected function _prepareLayout()
 	    {
 			//$this->getLayout()->getBlock('head')->addLinkRel('text/javascript','https://gateway.bluesnap.com/js/cse/v1.0.2/bluesnap.js');
 	        //$this->getLayout()->getBlock('head')->addItem('js','bluesnap/credit-card-detect.js');
 			//$this->getLayout()->getBlock('head')->addItem('js','bluesnap/payform.js');
 			//$this->getLayout()->getBlock('head')->addItem('js','bluesnap/bsadmin.js');		
 			//$this->getLayout()->getBlock('head')->addItem('js','lib/jquery/jquery-ui/jquery-ui.js');
 			//$this->getLayout()->getBlock('head')->addItem('js','lib/jquery/jquery-ui/jquery-ui.js');
 			//$this->getLayout()->getBlock('head')->addItem('skin_css', 'css/bluesnap/buynow/checkout.css');
 			//$this->getLayout()->getBlock('head')->addItem('js_css', 'lib/jquery/jquery-ui/jquery-ui.css');
 			return parent::_prepareLayout();

 	    }
}
