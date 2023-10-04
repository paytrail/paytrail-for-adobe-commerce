<?php

namespace Paytrail\PaymentService\Gateway\Request;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Paytrail\PaymentService\Model\Token\RequestData;
use Paytrail\SDK\Request\CitPaymentRequest;

class TokenPaymentDataBuilder implements BuilderInterface
{
    /**
     * TokenPaymentDataBuilder constructor.
     *
     * @param CitPaymentRequest $citPaymentRequest
     * @param RequestData $requestData
     */
    public function __construct(
        private readonly CitPaymentRequest $citPaymentRequest,
        private readonly RequestData $requestData
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
        $paytrailPayment = $this->citPaymentRequest;

        return [
            'order' => $buildSubject['order'],
            'request_data' => $this->requestData->setTokenPaymentRequestData(
                $paytrailPayment,
                $buildSubject['order'],
                $buildSubject['token_id'],
                $buildSubject['customer']
            )
        ];
    }
}
