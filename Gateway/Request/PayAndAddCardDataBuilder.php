<?php

namespace Paytrail\PaymentService\Gateway\Request;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Paytrail\PaymentService\Model\Payment\PaymentDataProvider;
use Paytrail\PaymentService\Model\UrlDataProvider;
use Paytrail\SDK\Request\PaymentRequest;

class PayAndAddCardDataBuilder implements BuilderInterface
{
    /**
     * PaymentDataBuilder constructor.
     *
     * @param PaymentRequest $paymentRequest
     * @param PaymentDataProvider $paymentDataProvider
     * @param UrlDataProvider $urlDataProvider
     */
    public function __construct(
        private readonly PaymentRequest      $paymentRequest,
        private readonly PaymentDataProvider $paymentDataProvider,
        private readonly UrlDataProvider $urlDataProvider
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
        $requestData = $this->changeCallbackUrl($this->paymentDataProvider->setPaymentRequestData(
            $paytrailPayment,
            $buildSubject['order']
        ));

        return [
            'order' => $buildSubject['order'],
            'request_data' => $requestData
        ];
    }

    /**
     * Changes callback url for PayAndAddCardCallback controller.
     *
     * @param PaymentRequest $paytrailPayment
     * @return mixed
     */
    private function changeCallbackUrl($paytrailPayment)
    {
        $paytrailPayment->setCallbackUrls($this->urlDataProvider->createPayAndAddCardCallbackUrl());

        return $paytrailPayment;
    }
}
