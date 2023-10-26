<?php

namespace Paytrail\PaymentService\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Paytrail\PaymentService\Model\Card\AddCardDataProvider;
use Paytrail\SDK\Request\AddCardFormRequest;

class AddCardDataBuilder implements BuilderInterface
{
    /**
     * AddCardDataBuilder constructor.
     *
     * @param AddCardDataProvider $addCardDataProvider
     * @param AddCardFormRequest $addCardFormRequest
     */
    public function __construct(
        private readonly AddCardDataProvider $addCardDataProvider,
        private readonly AddCardFormRequest $addCardFormRequest
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
        $addCardFormRequest = $this->addCardFormRequest;

        return [
            'add_card_form' => $this->addCardDataProvider->setAddCardFormRequestData($addCardFormRequest, $buildSubject)
        ];
    }
}
