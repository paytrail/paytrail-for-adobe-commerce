<?php

namespace Paytrail\PaymentService\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Paytrail\SDK\Request\GetTokenRequest;

class TokenRequestDataBuilder implements BuilderInterface
{
    /**
     * TokenRequestDataBuilder
     * @param GetTokenRequest $getTokenRequest
     */
    public function __construct(
        private GetTokenRequest $getTokenRequest
    ) {
    }

    /**
     * @inheritdoc
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $getTokenRequest = $this->getTokenRequest;

        return [
            'get_token_request' => $getTokenRequest->setCheckoutTokenizationId($buildSubject['tokenization_id'])
        ];
    }
}
