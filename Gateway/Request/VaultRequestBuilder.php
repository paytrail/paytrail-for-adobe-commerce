<?php
namespace Paytrail\PaymentService\Gateway\Request;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Paytrail\PaymentService\Model\Receipt\ProcessService;
use Psr\Log\LoggerInterface;

class VaultRequestBuilder implements BuilderInterface
{
    /**
     * VaultRequestBuilder constructor.
     *
     * @param ProcessService $processService
     * @param SubjectReader $subjectReader
     * @param LoggerInterface $log
     */
    public function __construct(
        private ProcessService $processService,
        private SubjectReader $subjectReader,
        private LoggerInterface $log
    ) {
    }

    /**
     * Build
     *
     * @param array $buildSubject
     * @return array
     * @throws LocalizedException
     */
    public function build(array $buildSubject)
    {
        $paymentDataObject = $this->subjectReader->readPayment($buildSubject);
        $amount = $this->subjectReader->readAmount($buildSubject);

        $order = $paymentDataObject->getOrder();
        $orderItems = $order->getItems();
        $payment = $paymentDataObject->getPayment();

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
            $this->processService->processError($errMsg);
        }

        return [
            'transaction_id' => $payment->getTransactionId(),
            'parent_transaction_id' => $payment->getParentTransactionId(),
            'amount' => $amount,
            'order' => $order
        ];
    }

    /**
     * Get tax rates.
     *
     * @param OrderItemInterface[] $items
     * @return array
     */
    private function getTaxRates($items)
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
