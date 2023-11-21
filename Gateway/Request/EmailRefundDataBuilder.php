<?php

namespace Paytrail\PaymentService\Gateway\Request;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Paytrail\PaymentService\Model\Receipt\ProcessService;
use Paytrail\SDK\Request\RefundRequest;
use Psr\Log\LoggerInterface;

class EmailRefundDataBuilder implements BuilderInterface
{
    /**
     * EmailRefundDataBuilder constructor.
     *
     * @param ProcessService $processService
     * @param SubjectReader $subjectReader
     * @param LoggerInterface $log
     * @param RefundRequest $refundRequest
     */
    public function __construct(
        private ProcessService $processService,
        private readonly SubjectReader $subjectReader,
        private readonly LoggerInterface $log,
        private readonly RefundRequest $refundRequest
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
        $payment    = $paymentDataObject->getPayment();

        $errMsg = null;

        if ($amount <= 0) {
            $errMsg = 'Invalid amount for refund.';
        }

        if (isset($errMsg)) {
            $this->log->error($errMsg);
            $this->processService->processError($errMsg);
        }

        return [
            'payment'               => $payment,
            'transaction_id'        => $payment->getTransactionId(),
            'parent_transaction_id' => $payment->getParentTransactionId(),
            'order'                 => $order,
            'refund_request'        => $this->refundRequest,
        ];
    }
}
