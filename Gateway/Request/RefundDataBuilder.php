<?php

namespace Paytrail\PaymentService\Gateway\Request;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Paytrail\PaymentService\Helper\Data;
use Psr\Log\LoggerInterface;

class RefundDataBuilder implements BuilderInterface
{
    /**
     * RefundDataBuilder constructor.
     *
     * @param Data                  $paytrailHelper
     * @param SubjectReader         $subjectReader
     * @param LoggerInterface       $log
     */
    public function __construct(
        private readonly Data $paytrailHelper,
        private readonly SubjectReader $subjectReader,
        private readonly LoggerInterface $log
    ) {
    }

    /**
     * Build request
     *
     * @param array $buildSubject
     *
     * @return array
     * @throws LocalizedException
     */
    public function build(array $buildSubject): array
    {
        $paymentDataObject = $this->subjectReader->readPayment($buildSubject);
        $amount            = $this->subjectReader->readAmount($buildSubject);

        $order      = $paymentDataObject->getOrder();
        $orderItems = $order->getItems();
        $payment    = $paymentDataObject->getPayment();

        $errMsg = null;

        if ($amount <= 0) {
            $errMsg = 'Invalid amount for refund.';
        }

        if (!$payment->getTransactionId()) {
            $errMsg = 'Invalid transaction ID.';
        }

        if (count($this->getTaxRates($orderItems)) !== 1) {
            $errMsg = 'Cannot refund order with multiple tax rates. Please refund offline.';
        }

        if (isset($errMsg)) {
            $this->log->error($errMsg);
            $this->paytrailHelper->processError($errMsg);
        }

        return [
            'payment'               => $payment,
            'transaction_id'        => $payment->getTransactionId(),
            'parent_transaction_id' => $payment->getParentTransactionId(),
            'amount'                => $amount,
            'order'                 => $order
        ];
    }

    /**
     * Get unique tax rates from order items
     *
     * @param array $items
     *
     * @return array
     */
    private function getTaxRates(array $items): array
    {
        $rates = [];
        foreach ($items as $item) {
            if ($item['price'] > 0) {
                $rates[] = round($item['vat'] * 100);
            }
        }

        return array_unique($rates, SORT_NUMERIC);
    }
}