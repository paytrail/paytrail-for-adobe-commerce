<?php

namespace Paytrail\PaymentService\Model\Payment;

interface DiscountGetterInterface
{
    /**
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return float
     */
    public function getDiscount(\Magento\Sales\Api\Data\OrderInterface $order): float;
}
