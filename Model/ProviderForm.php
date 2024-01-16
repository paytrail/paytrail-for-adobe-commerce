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
    public function getFormParams(PaymentResponse $paytrailPayment, string $paymentMethodId = null, $cardType = null)
    {
        $formParams = [
            'action' => $this->getFormAction($paytrailPayment, $paymentMethodId, $cardType),
            'inputs' => $this->getFormFields($paytrailPayment, $paymentMethodId, $cardType),
            'method' => self::FORM_SUBMIT_METHOD,
        ];

        return $formParams;
    }

    /**
     * GetFormAction function
     *
     * @param PaymentResponse $paytrailPayment
     * @param string $paymentMethodId
     * @param $cardType
     *
     * @return string
     */
    private function getFormAction(PaymentResponse $paytrailPayment, string $paymentMethodId, $cardType): string
    {
        $returnUrl = '';

        foreach ($paytrailPayment->getProviders() as $provider) {
            if ($provider->getId() == $paymentMethodId) {
                if ($cardType && strtolower($provider->getName()) != $cardType) {
                    continue;
                }
                $returnUrl = $provider->getUrl();

                break;
            }
        }

        return $returnUrl;
    }

    /**
     * GetFormFields function
     *
     * @param PaymentResponse $paytrailPayment
     * @param string $paymentMethodId
     * @param $cardType
     *
     * @return array
     */
    private function getFormFields(PaymentResponse $paytrailPayment, string $paymentMethodId, $cardType): array
    {
        $formFields = [];

        foreach ($paytrailPayment->getProviders() as $provider) {
            if ($provider->getId() == $paymentMethodId) {
                if ($cardType && strtolower($provider->getName()) != $cardType) {
                    continue;
                }
                foreach ($provider->getParameters() as $parameter) {
                    $formFields[] = [
                        'name'  => $parameter->name,
                        'value' => $parameter->value,
                    ];
                }

                break;
            }
        }

        return $formFields;
    }
}
