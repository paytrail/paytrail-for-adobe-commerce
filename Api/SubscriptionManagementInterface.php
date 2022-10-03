<?php

namespace Paytrail\PaymentService\Api;

/**
 * @api
 */
interface SubscriptionManagementInterface
{
    /**
     * Cancel active subscription
     *
     * @param string $subscriptionId
     * @return \Magento\Framework\Phrase
     */
    public function cancelSubscription(string $subscriptionId);

    /**
     * Change assigned card for subscription
     *
     * @param string $subscriptionId
     * @param string $cardId
     *
     * @return bool
     */
    public function changeSubscription(string $subscriptionId, string $cardId): bool;
}
