<?php

namespace Paytrail\PaymentService\Model\Payment;

/**
 * Extension point for custom cart/order total discounts.
 * Implement this interface and inject your implementation to DiscountSplitter using di.xml
 * @see \Paytrail\PaymentService\Model\Payment\DiscountSplitter::__construct()
 */
interface DiscountGetterInterface
{
    /**
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return float
     */
    public function getDiscount(\Magento\Sales\Api\Data\OrderInterface $order): float;
}
