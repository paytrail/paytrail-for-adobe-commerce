<?php

namespace Paytrail\PaymentService\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;

class InvoiceActivationDataBuilder implements BuilderInterface
{
    /**
     * @inheritdoc
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        return [
            'transaction_id' => $buildSubject['transaction_id']
        ];
    }
}
