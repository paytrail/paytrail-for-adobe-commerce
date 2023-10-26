<?php

namespace Paytrail\PaymentService\Gateway\Response;

use Magento\Framework\Message\ManagerInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class RefundHandler implements HandlerInterface
{
    /**
     * RefundHandler constructor.
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
     */
    public function handle(array $handlingSubject, array $response): void
    {
        $payment = $this->subjectReader->readPayment($handlingSubject);

        $payment       = $payment->getPayment();
        $transactionId = $payment->getTransactionId() . "-" . time();
        $payment->setIsTransactionClosed(true);
        $payment->setTransactionId($transactionId);
        $payment->setShouldCloseParentTransaction(false);

        $this->messageManager->addSuccessMessage(__('Paytrail refund successful.'));
    }
}
