<?php

namespace Paytrail\PaymentService\Gateway\Request;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Model\Payment\PaymentDataProvider;
use Paytrail\PaymentService\Model\Token\RequestData;
use Paytrail\SDK\Exception\ValidationException;
use Paytrail\SDK\Request\MitPaymentRequest;

class TokenPaymentDataBuilderMit implements BuilderInterface
{
    /**
     * TokenPaymentDataBuilder constructor.
     *
     * @param MitPaymentRequest $mitPaymentRequest
     * @param RequestData $requestData
     * @param PaymentDataProvider $paymentDataProvider
     */
    public function __construct(
        private readonly MitPaymentRequest   $mitPaymentRequest,
        private readonly RequestData         $requestData,
        private readonly PaymentDataProvider $paymentDataProvider
    ) {
    }

    /**
     * @inheritdoc
     *
     * @param array $buildSubject
     *
     * @return array
     * @throws NoSuchEntityException
     * @throws LocalizedException
     * @throws CheckoutException
     * @throws ValidationException
     */
    public function build(array $buildSubject): array
    {
        $paytrailPayment = $this->mitPaymentRequest;
        $this->paymentDataProvider->setPaymentRequestData(
            $paytrailPayment,
            $buildSubject['order'],
            'token_payment'
        );

        $paytrailPayment = $this->requestData->setTokenPaymentRequestData(
            $paytrailPayment,
            $buildSubject['order'],
            $buildSubject['token_id'],
            $buildSubject['customer']
        );

        return [
            'order'        => $buildSubject['order'],
            'request_data' => $paytrailPayment
        ];
    }
}
