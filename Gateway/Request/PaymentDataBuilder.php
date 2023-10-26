<?php

namespace Paytrail\PaymentService\Gateway\Request;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Paytrail\PaymentService\Model\Payment\PaymentDataProvider;
use Paytrail\SDK\Request\PaymentRequest;

class PaymentDataBuilder implements BuilderInterface
{
    /**
     * PaymentDataBuilder constructor.
     *
     * @param PaymentRequest $paymentRequest
     * @param PaymentDataProvider $paymentDataProvider
     */
    public function __construct(
        private readonly PaymentRequest      $paymentRequest,
        private readonly PaymentDataProvider $paymentDataProvider,
    ) {
    }

    /**
     * @inheritdoc
     *
     * @param array $buildSubject
     * @return array
     * @throws NoSuchEntityException
     */
    public function build(array $buildSubject): array
    {
        $paytrailPayment = $this->paymentRequest;

        return [
            'order' => $buildSubject['order'],
            'request_data' => $this->paymentDataProvider->setPaymentRequestData(
                $paytrailPayment,
                $buildSubject['order'],
                $buildSubject['payment_method']
            )
        ];
    }
}
