<?php

namespace Paytrail\PaymentService\Model\Receipt;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface as TransactionBuilderInterface;

class PaymentTransaction
{
    /**
     * @param Order\Payment\Transaction\BuilderInterface $transactionBuilder
     */
    public function __construct(
        private TransactionBuilderInterface $transactionBuilder
    ) {
    }

    /**
     * AddPaymentTransaction function
     *
     * @param Order $order
     * @param $transactionId
     * @param array $details
     * @return \Magento\Sales\Api\Data\TransactionInterface
     */
    public function addPaymentTransaction(Order $order, $transactionId, array $details = [])
    {
        /** @var \Magento\Framework\DataObject|\Magento\Sales\Api\Data\OrderPaymentInterface |mixed|null $payment */
        $payment = $order->getPayment();

        /** @var \Magento\Sales\Api\Data\TransactionInterface $transaction */
        $transaction = $this->transactionBuilder
            ->setPayment($payment)->setOrder($order)
            ->setTransactionId($transactionId)
            ->setAdditionalInformation([Transaction::RAW_DETAILS => (array) $details])
            ->setFailSafe(true)
            ->build(Transaction::TYPE_CAPTURE);
        $transaction->setIsClosed(0);
        return $transaction;
    }
}
