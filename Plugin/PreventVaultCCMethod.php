<?php

namespace Paytrail\PaymentService\Plugin;

use Magento\Payment\Block\Form\Container;
use Paytrail\PaymentService\Gateway\Config\Config;

class PreventVaultCCMethod
{
    /**
     * Plugin for getMethods function.
     *
     * @param Container $subject
     * @param array $result
     * @return array
     */
    public function afterGetMethods(Container $subject, array $result): array
    {
        $methods = [];
        foreach ($result as $paymentMethd) {
            if (!($paymentMethd->getCode() === Config::CC_VAULT_CODE)) {
                $methods[] = $paymentMethd;
            }
        }

        return $methods;
    }
}
