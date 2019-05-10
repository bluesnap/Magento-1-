<?php

class Bluesnap_Payment_Adminhtml_Bluesnap_LoggerController
    extends Mage_Adminhtml_Controller_Action
{
    /**
     * Api log controller action.
     *
     * @return void
     */
    public function indexAction()
    {
        $this->loadLayout()
            ->renderLayout();
    }
}
