<?php

/**
 * IPN request processing
 * http://home.bluesnap.com/ndoc/buyNowDoc2/Default.htm#InstantNotificationsIPN/Cancel.htm
 *
 * http://home.bluesnap.com/ndoc/buyNowDoc/Default.htm#Cancel.htm%3FTocPath%3DInstant%2520Payment%2520Notifications%2520(IPN)%2520%7CNotification%2520Types%7CDefault%2520Notifications%7C_____2
 *
 * A Cancel notification is issued when a transaction is cancelled due to
 * BlueSnap manual review process. The following parameters are sent to the
 * Merchant when a Cancel transaction is generated.
 * With the new IPN mechanism (which is being released throughout December 2012),
 * this IPN type is being merged with the Decline IPN.
 *
 *
 * Decline
 * This notification is issued when a transaction is declined due to BlueSnap
 * manual review process.The following parameters are sent to the Merchant when
 * a Decline transaction is generated.
 *
 * The following symbols indicate the type of update to the relevant parameters:
 *
 *
 * Cancellation Refund
 * A Cancellation Refund notification is sent when a subscription is cancelled
 * and a refund is processed. The following
 *
 * parameters are sent to the Merchant when a Cancellation Refund transaction
 * is generated.
 *
 * The following symbols indicate the type of update to the relevant parameters:
 */
class Bluesnap_Payment_Model_Ipn extends Bluesnap_Payment_Model_Api_Abstract
{
    const PARAM_TRANSATION_TYPE = 'transactionType';
    const PARAM_REFERENCE_NUMBER = 'referenceNumber';
    const PARAM_REVERSAL_REF_NUM = 'reversalRefNum';
    const PARAM_ORDER_INCREMENT_ID = 'magento_order_id';
    const PARAM_CONTRACT_ID = 'contractId';
    const PARAM_AUTH_KEY = 'authKey';

    const TRANSACTION_TYPE_CHARGE = 'CHARGE';
    /**
     * Ipn refund
     * http://home.bluesnap.com/ndoc/buyNowDoc/Default.htm#InstantNotificationsIPN/Refund.htm%3FTocPath%3DInstant%2520Payment%2520Notifications%2520(IPN)%2520%7CNotification%2520Types%7CDefault%2520Notifications%7C_____10
     */
    const TRANSACTION_TYPE_REFUND = 'REFUND';
    /**
     * Subscription cancellation and refund - out of scope  but still I
     * see many cancellation_refund in log
     */
    const TRANSACTION_TYPE_CANCELLATION_REFUND = 'CANCELLATION_REFUND';
    /**
     * Same as decline - see description above
     */
    const TRANSACTION_TYPE_CANCEL = 'DECLINE';
    /**
     * Declined after manual review
     * http://home.bluesnap.com/ndoc/buyNowDoc/Default.htm#InstantNotificationsIPN/Decline.htm%3FTocPath%3DInstant%2520Payment%2520Notifications%2520(IPN)%2520%7CNotification%2520Types%7CDefault%2520Notifications%7C_____8
     */
    const TRANSACTION_TYPE_DECLINE = 'DECLINE';
    /**
     * http://home.bluesnap.com/ndoc/buyNowDoc/Default.htm#InstantNotificationsIPN/Chargeback.htm%3FTocPath%3DInstant%2520Payment%2520Notifications%2520(IPN)%2520%7CNotification%2520Types%7CDefault%2520Notifications%7C_____6
     */
    const TRANSACTION_TYPE_CHARGEBACK = 'CHARGEBACK';

    const EXCEPTION_INVALID_AUTH_KEY = 1;
    const EXCEPTION_UNKNOWN_TRANSACTION_TYPE = 2;
    const EXCEPTION_ORDER_NOT_FOUND = 3;
    const EXCEPTION_ORDER_CANNOT_INVOICE = 4;
    const EXCEPTION_ORDER_CANNOT_CANCEL = 5;

    /** @var bool $isDebugMode Whether module is in debug mode */
    protected $isDebugMode;

    /** @var string $debugLogName Log file name */
    protected $debugLogName;

    /**
     * Constructor
     *
     * Set debug mode flag, log file name.
     */
    public function __construct()
    {
        $this->isDebugMode = Mage::helper('bluesnap')->isApiDebugMode();
        $this->debugLogName = 'bluesnap_ipn.log';
    }

    /**
     * Process IPN request:
     * - validate Authentication Key;
     * - call processing method depending on transaction type.
     *
     * @param Mage_Core_Controller_Request_Http $request Request object.
     *
     * @return bool|void
     * @throws Mage_Core_Exception
     */
    public function processTransactionRequest(Mage_Core_Controller_Request_Http $request)
    {
        // Mage::log("Ipn::processTransactionRequest: ".var_export($request->getParams(),1),Zend_Log::DEBUG);

        $this->logRequestDebug($request);

        // Validate auth key.
        if (!$this->isRequestAuthKeyValid($request)) {
            $e = Mage::exception(
                'Bluesnap_Payment',
                'Invalid auth key',
                self::EXCEPTION_INVALID_AUTH_KEY
            );
            Mage::logException($e);
        }

        // Process request.
        $transactionType = $request->getParam(self::PARAM_TRANSATION_TYPE);

        switch ($transactionType) {
            case self::TRANSACTION_TYPE_CHARGE:
                return $this->processChargeTransactionRequest($request);
                break;
            case self::TRANSACTION_TYPE_CANCELLATION_REFUND:
            case self::TRANSACTION_TYPE_REFUND:
            case self::TRANSACTION_TYPE_DECLINE:
            case self::TRANSACTION_TYPE_CHARGEBACK:
                return $this->processRefundDeclineTransactionRequest($request);
                break;
            default:
                throw Mage::exception(
                    'Bluesnap_Payment',
                    Mage::helper('bluesnap')->__(
                        'Unknown transaction type %s',
                        $transactionType
                    ),
                    self::EXCEPTION_UNKNOWN_TRANSACTION_TYPE
                );
        }
    }

    /**
     * Log request information.
     *
     * @param Mage_Core_Controller_Request_Http $request Request object.
     *
     * @return void
     */
    protected function logRequestDebug(
        Mage_Core_Controller_Request_Http $request
    ) {
        $message = Mage::helper('bluesnap')->requestToString($request);
        $this->getLogger()->logSuccess(
            '',
            $message,
            '',
            'Ipn',
            'Ipn ' . $request->getParam(self::PARAM_TRANSATION_TYPE),
            $request->getParam(self::PARAM_ORDER_INCREMENT_ID)
        );

        if ($this->isDebugMode) {
            $this->logDebug($message);
        }
    }

    /**
     * Log debug information.
     *
     * @param string $message Message string.
     *
     * @return void
     */
    protected function logDebug($message)
    {
        if ($this->isDebugMode) {
            Mage::log($message, Zend_Log::DEBUG, $this->debugLogName, true);
        }
    }

    /**
     * Check if authKey is valid.
     *
     * @param Mage_Core_Controller_Request_Http $request Request object.
     *
     * @return bool
     */
    protected function isRequestAuthKeyValid(
        Mage_Core_Controller_Request_Http $request
    ) {
        if (is_null($request->getParam(self::PARAM_REFERENCE_NUMBER))
            || is_null($request->getParam(self::PARAM_CONTRACT_ID))
            || is_null($request->getParam(self::PARAM_AUTH_KEY))
        ) {
            return false;
        }

        $validAuthKeyStr = $request->getParam(self::PARAM_REFERENCE_NUMBER)
            . $request->getParam(self::PARAM_CONTRACT_ID)
            . Mage::helper('bluesnap')->getDataProtectionKey();

        return (md5($validAuthKeyStr) == $request->getParam(self::PARAM_AUTH_KEY));
    }

    /**
     * Create invoice.
     *
     * @param Mage_Sales_Model_Order $order         Order object.
     * @param string                 $transactionId Bluesnap transaction id.
     *
     * @return Bluesnap_Payment_Model_Ipn
     * @throws Exception
     */
    protected function createInvoice(Mage_Sales_Model_Order $order, $transactionId)
    {
        $helper = Mage::helper('bluesnap');

        if (!$order->canInvoice()) {
            throw new Exception($helper->__('Cannot create an invoice.'));
        }

        /** @var Mage_Sales_Model_Order_Invoice $invoice */
        $invoice = Mage::getModel('sales/service_order', $order)
            ->prepareInvoice();

        if (!$invoice->getTotalQty()) {
            throw new Exception(
                $helper->__('Cannot create an invoice without products.')
            );
        }

        $invoice
            ->setRequestedCaptureCase(
                Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE
            )
            ->addComment(
                'Invoice generated automatically. '
                . 'Triggered by Bluesnap IPN charge request.'
            )
            ->setTransactionId($transactionId)
            ->setBluesnapGrandTotal($order->getBluesnapGrandTotal())
            ->setBluesnapCurrencyCode($order->getBluesnapCurrencyCode());

        $transactionSave = Mage::getModel('core/resource_transaction')
            ->addObject($invoice)
            ->addObject($order);

        $transactionSave->save();

        return $this;
    }

    /**
     * Process CHARGE request,
     * create order invoice,
     * set order state to Processing.
     *
     * @param Mage_Core_Controller_Request_Http $request Request object.
     *
     * @return bool
     * @throws Exception
     * @throws Mage_Core_Exception
     */
    public function processChargeTransactionRequest(
        Mage_Core_Controller_Request_Http $request
    ) {
        $orderIncrementId = $request->getParam(self::PARAM_ORDER_INCREMENT_ID, null);
        $bsTransactionId = $request->getParam(self::PARAM_REFERENCE_NUMBER);

        if ($orderIncrementId && $bsTransactionId) {
            $order = $this->loadOrderByIncrementId($orderIncrementId);

            if ($order->getId()) {
                $payment = $order->getPayment();
                /* @var $payment Mage_Sales_Model_Order_Payment */
                $paymentMethod = $payment->getMethod();

                if ($paymentMethod == 'cse') {
                    $invoiceCollection = $order->getInvoiceCollection();
                    if ($invoiceCollection->getSize() == 0) {
                        $this->createInvoice($order, $bsTransactionId);
                        $invoiceCollection = $order->getInvoiceCollection();
                    }

                    foreach ($invoiceCollection as $invoice) {
                        /* @var $invoice Mage_Sales_Model_Order_Invoice */
                        if ($bsTransactionId != $invoice->getTransactionId()) {
                            continue;
                        }

                        $invoice
                            ->setIsTransactionClosed(true)
                            ->setState(Mage_Sales_Model_Order_Invoice::STATE_PAID)
                            ->save();

                        if ($invoice->canCapture()) {
                            //save ipn params
                            //BSNPMG-101
                            //BSNPMG-102
                            $payment->setAdditionalInformation($request->getParams());
                            $payment->setCcLast4($request->getParam('creditCardLastFourDigits'));
                            $payment->setCcType($request->getParam('creditCardType'));
                            $creditCardExpDate = explode('/', $request->getParam('creditCardExpDate'));

                            $payment->setCcExpMonth($creditCardExpDate[0]);
                            $payment->setCcExpYear($creditCardExpDate[1]);
                            $payment->setCcAvsStatus($request->getParam("avsResponse"));

                            $payment->setTransactionId($bsTransactionId);
                            $payment->setIsTransactionClosed(true);
                            $payment->registerCaptureNotification($order->getBaseGrandTotal());

                            $order->setBluesnapTotalInvoiced($request->getParam('invoiceChargeAmount'));
                            $order->setBluesnapTotalPaid($request->getParam('invoiceChargeAmount'));

                            $order->save();
                            $invoice->sendEmail();
                            break;
                        }
                    }
                } elseif ($paymentMethod == 'buynow') {
                    $invoice = $order->getInvoiceCollection()->getFirstItem();
                    if (!$invoice->getId()) {
                        throw new Exception(
                            'No invoice found for order: '
                            . $order->getIncrementId()
                        );

                    }
                    /* @var $invoice Mage_Sales_Model_Order_Invoice */

                    $invoice->setIsTransactionClosed(true);
                    $invoice->setTransactionId($bsTransactionId);

                    //email not sent when redirecting
                    //see Mage_Checkout_Model_Type_Onepage line 814
                    ///BSNPMG-103
                    //I want invoice to be synced with bsnap. Can I?
                    //  $invoice->setIncrementId($bsTransactionId);
                    //$invoice->save();

                    //save ipn params
                    //BSNPMG-101
                    //BSNPMG-102
                    $payment->setAdditionalInformation($request->getParams());
                    $payment->setCcLast4($request->getParam('creditCardLastFourDigits'));
                    $payment->setCcType($request->getParam('creditCardType'));
                    $creditCardExpDate = explode('/', $request->getParam('creditCardExpDate'));

                    $payment->setCcExpMonth($creditCardExpDate[0]);
                    $payment->setCcExpYear($creditCardExpDate[1]);
                    $payment->setCcAvsStatus($request->getParam("avsResponse"));

                    //creditCardLastFourDigits:
                    // creditCardExpDate: string = "2/2016"

                    $payment->setTransactionId($bsTransactionId);
                    $payment->setIsTransactionClosed(true);

                    /**
                     * @TODO: need to refactor and place register capture notification into one method
                     */
                    if ($invoice->canCapture()) {
                        $payment->registerCaptureNotification($order->getBaseGrandTotal());
                        //$payment->save();
                        $order->setBluesnapReferenceNumber($bsTransactionId);

                        $order->setBluesnapTotalInvoiced($request->getParam('invoiceChargeAmount'));
                        $order->setBluesnapTotalPaid($request->getParam('invoiceChargeAmount'));

                        //really captured amount
                        $order->addStatusHistoryComment(
                            sprintf(
                                "Bluesnap BuyNow captured amount of %s%s online. "
                                . "Transaction ID: \"%s\".",
                                $request->getParam('invoiceChargeAmount'),
                                $request->getParam('invoiceChargeCurrency'),
                                $bsTransactionId
                            )
                        );

                        $order->save();
                        //@todo: change to invoice email!

                        //BSNPMG-115
                        $order->queueNewOrderEmail();

                        //$order->sendNewOrderEmail();
                        //BSNPMG-80 should be invoice email, not order email
                        $invoice->sendEmail();
                    }
                } else {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Load order by increment ID, throw exception if order not found.
     *
     * @param string $incrementId Order increment id.
     *
     * @return Mage_Sales_Model_Order
     * @throws Mage_Core_Exception
     */
    protected function loadOrderByIncrementId($incrementId)
    {
        $order = Mage::getModel('sales/order')
            ->loadByIncrementId($incrementId);

        if (!$order->getId()) {
            throw Mage::exception(
                'Bluesnap_Payment',
                Mage::helper('bluesnap')->__(
                    'Order #%s was not found.',
                    $incrementId
                ),
                self::EXCEPTION_ORDER_NOT_FOUND
            );
        }

        return $order;
    }

    /**
     * Process REFUND and DECLINE request
     * Cancel Order
     * @param Mage_Core_Controller_Request_Http $request Request object.
     *
     * @return bool
     * @throws Exception
     * @throws Mage_Core_Exception
     */
    public function processRefundDeclineTransactionRequest(
        Mage_Core_Controller_Request_Http $request
    ) {
        $orderIncrementId = $request->getParam(self::PARAM_ORDER_INCREMENT_ID);
        $referenceNumber = $request->getParam(self::PARAM_REFERENCE_NUMBER);
        $reversalRefNum = $request->getParam(self::PARAM_REVERSAL_REF_NUM);

        $order = $this->loadOrderByIncrementId($orderIncrementId);

        $transactionType = $request->getParam(self::PARAM_TRANSATION_TYPE);
        switch ($transactionType) {
            case self::TRANSACTION_TYPE_REFUND:
            case self::TRANSACTION_TYPE_CANCELLATION_REFUND:
            case self::TRANSACTION_TYPE_CHARGEBACK:
                $duplicatedResponse = false;
                $creditmemosCollection = $order->getCreditmemosCollection();

                foreach ($creditmemosCollection as $creditmemo) {
                    $creditmemoReversalRefNum = $creditmemo
                        ->getBluesnapReversalRefNum();
                    if ($creditmemoReversalRefNum === $reversalRefNum) {
                        // It means that this refund response has
                        // been already handled.
                        $duplicatedResponse = true;
                    }
                }

                if ($duplicatedResponse) {
                    break;
                }

                //BSNPMG-116
                //flag to disable refund loop in payment method refund
                Mage::register('ipn_transaction_refund', true);

                if ($order->getInvoiceCollection()->getSize() == 0) {
                    throw new Bluesnap_Payment_Exception(
                        'Can not refund order: ' . $order->getIncrementId()
                    );
                }

                $service = Mage::getModel('sales/service_order', $order);

                $invoice = false;
                $creditmemo = false;

                $refundAmount = abs($request->getParam('invoiceChargeAmount'));

                foreach ($order->getInvoiceCollection() as $invoice) {
                    //BSNPMG-116
                    if ($invoice->canRefund() === false) {
                        continue;
                    }

                    $data = array(
                        'shipping_amount' => 0,
                        'grand_total' => 0,
                        'base_grand_total' => 0,
                        'adjustment_positive' => $refundAmount,
                        'qtys' => array(0 => 0),
                    );
                    $creditmemo = $service->prepareInvoiceCreditmemo($invoice, $data);
                    $creditmemo
                        ->setShippingAmount(0)
                        ->setGrandTotal($data['adjustment_positive'])
                        ->setRefundRequested(true)
                        ->setOfflineRequested(false)
                        ->setPaymentRefundDisallowed(false)
                        ->register();

                    $creditmemo
                        ->setBluesnapCurrencyCode($request->getParam('invoiceChargeCurrency'))
                        ->setBluesnapGrandTotal($refundAmount)
                        ->setBluesnapReversalRefNum($reversalRefNum)
                        ->save();
                }

                // Really refunded amount.
                $order->setBluesnapTotalRefunded($refundAmount);

                $statusHistory = $transactionType == self::TRANSACTION_TYPE_CHARGEBACK
                    ? 'chargeback'
                    : 'refunded';

                //BSNPMG-128
                $reason = $request->getParam('reversalReason')
                    ? $request->getParam('reversalReason')
                    : $request->getParam('cancelReason');

                $refundCurrency = Mage::getModel('directory/currency')
                    ->load($request->getParam('invoiceChargeCurrency'));

                $order
                    ->addStatusHistoryComment(
                        sprintf(
                            'Ipn Refund notification. Amount: %s. '
                            . 'ReversalRefNum: %s. Reversal Reason: %s.',
                            $refundCurrency->formatTxt($request->getParam('invoiceChargeAmount')),
                            $reversalRefNum,
                            $reason
                        ),
                        $statusHistory
                    )
                    ->setIsCustomerNotified(1);

                $order->setStatus($statusHistory);
                $order->setIsInProcess(true);

                Mage::getModel('core/resource_transaction')
                    ->addObject($creditmemo)
                    ->addObject($invoice)
                    ->addObject($order)
                    ->save();

                if (!empty($statusHistory)) {
                    $order->setStatus($statusHistory);
                    $order->save();
                }

                try {
                    if ($transactionType == self::TRANSACTION_TYPE_CHARGEBACK) {
                        $this->_emailHelper()->sendPaymentChargebackEmail(
                            $order,
                            $reason,
                            'onepage',
                            $referenceNumber
                        );
                    } else {
                        $this->_emailHelper()->sendPaymentRefundedEmail(
                            $order,
                            $reason,
                            'onepage',
                            $referenceNumber
                        );
                    }
                } catch (Exception $e) {
                    Mage::logException($e);
                }

                //send email from IPN, only here I have all relevant details
                //    try {
                //    $this->_emailHelper()->sendPaymentRefundedEmail($order);
                //} catch(Exception $e) {
                //    Mage::logException($e);
                //    $this->getLogger()->logError("","",0,$e->getMessage(),"cse::refund",$payment->getOrder()->getIncrementId());
                // }
                break;
            case self::TRANSACTION_TYPE_DECLINE:
                foreach ($order->getInvoiceCollection() as $invoice) {
                    if ($invoice && $invoice->canCancel()) {
                        $invoice
                            ->cancel()
                            ->save();
                    }
                }

                if (!$order->canCancel()) {
                    throw Mage::exception(
                        'Bluesnap_Payment',
                        Mage::helper('bluesnap')->__(
                            'Cannot cancel order #%s',
                            $order->getIncrementId()
                        ),
                        self::EXCEPTION_ORDER_CANNOT_CANCEL
                    );
                }

                $order->cancel();

                $statusHistory = 'declined';

                $order
                    ->addStatusHistoryComment(
                        'Order declined by IPN request',
                        $statusHistory
                    )
                    ->setIsCustomerNotified(1);

                $this->_emailHelper()->sendPaymentDeclinedEmail($order);
                $order->setStatus($statusHistory)->save();
                break;
        }

        //see invoiceController::_saveInvoice()
        $this->logRequestDebug($request);

        return true;
    }

    /**
     * Email helper getter.
     *
     * @return Bluesnap_Payment_Helper_Email
     */
    protected function _emailHelper()
    {
        return Mage::getSingleton('Bluesnap_Payment_Helper_Email');
    }

    /**
     * Get `OK' response string.
     *
     * @return string
     */
    public function getOkResponseString()
    {
        $responseText = 'OK'
            . Mage::helper('bluesnap')->getDataProtectionKey();

        return md5($responseText);
    }

    /**
     * Load order by increment ID, throw exception if order not found.
     *
     * @param int $orderId Order id.
     *
     * @return Mage_Sales_Model_Order
     * @throws Mage_Core_Exception
     */
    protected function loadOrderById($orderId)
    {
        $order = Mage::getModel('sales/order')->load($orderId);
        if (!$order->getId()) {
            throw Mage::exception(
                'Bluesnap_Payment',
                Mage::helper('bluesnap')->__(
                    'Order id #%s was not found.',
                    $orderId
                ),
                self::EXCEPTION_ORDER_NOT_FOUND
            );
        }

        return $order;
    }

    /**
     * Check whether all order items were invoiced.
     *
     * @param Mage_Sales_Model_Order $order Order object.
     *
     * @return bool
     */
    protected function isOrderAllInvoiced(Mage_Sales_Model_Order $order)
    {
        foreach ($order->getAllItems() as $item) {
            if ($item->getQtyToInvoice() > 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create full invoice for given order
     *
     * @param Mage_Sales_Model_Order $order Order object.
     *
     * @return Mage_Sales_Model_Order_Invoice
     * @throws Mage_Core_Exception
     */
    protected function createOrderInvoice(Mage_Sales_Model_Order $order)
    {
        if (!$order->canInvoice()) {
            throw Mage::exception(
                'Bluesnap_Payment',
                Mage::helper('bluesnap')->__(
                    'Order %s does not allow creating an invoice',
                    $order->getIncrementId()
                ),
                self::EXCEPTION_ORDER_CANNOT_INVOICE
            );
        }

        return Mage::getModel('sales/service_order', $order)->prepareInvoice();
    }
}
