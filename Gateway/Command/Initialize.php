<?php

namespace Paytrail\PaymentService\Gateway\Command;

use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;

class Initialize implements CommandInterface
{
    /**
     * Initialize constructor.
     *
     * @param SubjectReader $subjectReader
     */
    public function __construct(
        private SubjectReader $subjectReader
    ) {
    }

    /**
     * Execute function
     *
     * @param array $commandSubject
     * @return $this|ResultInterface|null
     */
    public function execute(array $commandSubject)
    {
        /** @var PaymentDataObjectInterface $payment */
        $payment = $this->subjectReader->readPayment($commandSubject);
        $stateObject = $this->subjectReader->readStateObject($commandSubject);

        /** @var InfoInterface $payment */
        $payment = $payment->getPayment();
        $payment->setIsTransactionPending(true);
        $payment->setIsTransactionIsClosed(false);
        $order = $payment->getOrder();
        $order->setCanSendNewEmailFlag(false);

        $stateObject->setIsNotified(false);

        $stateObject->setState(Order::STATE_PENDING_PAYMENT);
        $stateObject->setStatus(Order::STATE_PENDING_PAYMENT);

        $stateObject->setIsNotified(false);

        return $this;
    }
}
