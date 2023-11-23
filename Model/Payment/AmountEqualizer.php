<?php

namespace Paytrail\PaymentService\Model\Payment;

use Paytrail\SDK\Request\PaymentRequest;

class AmountEqualizer
{
    /**
     * Equals request total amount and items amounts.
     *
     * @param $paytrailPayment
     * @return PaymentRequest
     */
    public function equal($paytrailPayment)
    {
        $totalAmount = $paytrailPayment->getAmount();
        $summaryAmount = 0;

        foreach ($paytrailPayment->getItems() as $item) {
            $summaryAmount += $item->getUnitPrice() * $item->getUnits();
        }

        if ($totalAmount === $summaryAmount) {
            return $paytrailPayment;
        }

        if ($summaryAmount > $totalAmount) {
            $equalAmount = $summaryAmount - $totalAmount;
            foreach ($paytrailPayment->getItems() as $item) {
                if ($item->getProductCode() === 'shipping-row') {
                    $item->setUnitPrice($item->getUnitPrice() - $equalAmount);
                }
            }
        }

        if ($totalAmount > $summaryAmount) {
            $equalAmount = $totalAmount - $summaryAmount;
            foreach ($paytrailPayment->getItems() as $item) {
                if ($item->getProductCode() === 'shipping-row') {
                    $item->setUnitPrice($item->getUnitPrice() + $equalAmount);
                }
            }
        }

        return $paytrailPayment;
    }
}
