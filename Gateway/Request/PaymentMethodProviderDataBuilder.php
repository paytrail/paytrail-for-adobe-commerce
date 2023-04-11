<?php

namespace Paytrail\PaymentService\Gateway\Request;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Paytrail\PaymentService\Model\PaymentMethod\PaymentMethodDataProvider;

class PaymentMethodProviderDataBuilder implements BuilderInterface
{
    /**
     * @param SubjectReader $subjectReader
     * @param PaymentMethodDataProvider $paymentMethodDataProvider
     */
    public function __construct(
        private readonly SubjectReader $subjectReader,
        private readonly PaymentMethodDataProvider $paymentMethodDataProvider
    ) {
    }

    /**
     * @inheritdoc
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        $amount = $this->subjectReader->readAmount($buildSubject);

        return [
            'amount' => round($amount * 100),
            'locale' => $this->paymentMethodDataProvider->getStoreLocaleForPaymentProvider(),
            'groups' => []
        ];
    }
}
