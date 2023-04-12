<?php

namespace Paytrail\PaymentService\Gateway\Response;

use Magento\Framework\Message\ManagerInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class EmailRefundHandler implements HandlerInterface
{
    /**
     * EmailRefundHandler constructor.
     *
     * @param ManagerInterface $messageManager
     * @param SubjectReader    $subjectReader
     */
    public function __construct(
        private readonly ManagerInterface $messageManager,
        private readonly SubjectReader $subjectReader
    ) {
    }

    /**
     * Handle function
     *
     * @param array $handlingSubject
     * @param array $response
     *
     * @return array
     */
    public function handle(array $handlingSubject, array $response): array
    {
        $payment = $this->subjectReader->readPayment($handlingSubject);

        $payment       = $payment->getPayment();
        $transactionId = $payment->getTransactionId() . "-" . time();
        $payment->setIsTransactionClosed(true);
        $payment->setTransactionId($transactionId);
        $payment->setShouldCloseParentTransaction(false);

        $this->messageManager->addSuccessMessage(__('Paytrail Email Refund message successfully sent.'));

        return $response;
    }
}
