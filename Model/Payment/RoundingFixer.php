<?php

namespace Paytrail\PaymentService\Model\Payment;
class RoundingFixer
{
    /**
     * Correct rounding errors
     *
     * Adds a new item to the items array to correct rounding errors
     *
     * @param array $items
     * @param float $discountedTotal
     * @param float $itemDiscountedTotal
     */
    public function correctRoundingErrors(array &$items, float $discountedTotal, float $itemDiscountedTotal): void
    {
        $delta = bcsub($discountedTotal, $itemDiscountedTotal, 2);
        if ($delta == 0) {
            return;
        }

        $items[] = [
            'title'  => 'Discount rounding correction',
            'code'   => 'discount-rounding-correction',
            'price'  => $delta,
            'amount' => 1,
            'vat'    => 0,
        ];
    }
}
