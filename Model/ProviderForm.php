<?php

namespace Paytrail\PaymentService\Model;

use Paytrail\SDK\Model\Provider;
use Paytrail\SDK\Response\PaymentResponse;

class ProviderForm
{
    const FORM_SUBMIT_METHOD = 'POST';

    /**
     * @param $paytrailPayment
     * @param null $paymentMethodId
     *
     * @return array
     */
    public function getFormParams(PaymentResponse $paytrailPayment, string $paymentMethodId = null)
    {
        $formParams = [
            'action' => $this->getFormAction($paytrailPayment, $paymentMethodId),
            'inputs' => $this->getFormFields($paytrailPayment, $paymentMethodId),
            'method' => self::FORM_SUBMIT_METHOD,
        ];

        return $formParams;
    }

    /**
     * GetFormAction function
     *
     * @param PaymentResponse $paytrailPayment
     * @param string $paymentMethodId
     *
     * @return string
     */
    private function getFormAction(PaymentResponse $paytrailPayment, string $paymentMethodId): string
    {
        $returnUrl = '';

        foreach ($paytrailPayment->getProviders() as $provider) {
            if ($provider->getId() == $paymentMethodId) {
                $returnUrl = $provider->getUrl();
            }
        }

        return $returnUrl;
    }

    /**
     * GetFormFields function
     *
     * @param PaymentResponse $paytrailPayment
     * @param string $paymentMethodId
     *
     * @return array
     */
    private function getFormFields(PaymentResponse $paytrailPayment, string $paymentMethodId): array
    {
        $formFields = [];

        foreach ($paytrailPayment->getProviders() as $provider) {
            if ($provider->getId() == $paymentMethodId) {
                foreach ($provider->getParameters() as $parameter) {
                    $formFields[] = [
                        'name'  => $parameter->name,
                        'value' => $parameter->value,
                    ];
                }
            }
        }

        return $formFields;
    }
}
