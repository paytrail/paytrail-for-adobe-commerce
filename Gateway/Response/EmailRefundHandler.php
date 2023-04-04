<?php
namespace Paytrail\PaymentService\Gateway\Response;

use Magento\Framework\Message\ManagerInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class EmailRefundHandler implements HandlerInterface
{
    /**
     * @var ManagerInterface
     */
    private ManagerInterface $messageManager;

    /**
     * @var SubjectReader
     */
    private SubjectReader $subjectReader;

    /**
     * RefundHandler constructor.
     *
     * @param ManagerInterface $messageManager
     * @param SubjectReader $subjectReader
     */
    public function __construct(
        ManagerInterface $messageManager,
        SubjectReader $subjectReader
    ) {
        $this->messageManager = $messageManager;
        $this->subjectReader = $subjectReader;
    }

    /**
     * Handle function
     *
     * @param array $handlingSubject
     * @param array $response
     *
     * @return array
     */
    public function handle(array $handlingSubject, array $response)
    {
        $payment = $this->subjectReader->readPayment($handlingSubject);

        $payment = $payment->getPayment();
        $transactionId = $payment->getTransactionId() . "-" . time();
        $payment->setIsTransactionClosed(true);
        $payment->setTransactionId($transactionId);
        $payment->setShouldCloseParentTransaction(false);

        $this->messageManager->addSuccessMessage(__('Paytrail Email Refund message successfully sent.'));
        
        return $response;
    }
}
