<?php

namespace Paytrail\PaymentService\Model;

use Paytrail\SDK\Response\PaymentResponse;

class ProviderForm
{
    public const FORM_SUBMIT_METHOD = 'POST';

    /**
     * GetFormParams function
     *
     * @param PaymentResponse $paytrailPayment
     * @param string|null $paymentMethodId
     * @param string|null $cardType
     *
     * @return array
     */
    public function getFormParams(
        PaymentResponse $paytrailPayment,
        ?string         $paymentMethodId = null,
        ?string         $cardType = null
    ) {
        return [
            'action' => $this->getFormAction($paytrailPayment, $paymentMethodId, $cardType),
            'inputs' => $this->getFormFields($paytrailPayment, $paymentMethodId, $cardType),
            'method' => self::FORM_SUBMIT_METHOD,
        ];
    }

    /**
     * GetFormAction function
     *
     * @param PaymentResponse $paytrailPayment
     * @param string $paymentMethodId
     * @param string|null $cardType
     *
     * @return string
     */
    private function getFormAction(PaymentResponse $paytrailPayment, string $paymentMethodId, ?string $cardType): string
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
     * @param string|null $cardType
     *
     * @return array
     */
    private function getFormFields(PaymentResponse $paytrailPayment, string $paymentMethodId, ?string $cardType): array
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
