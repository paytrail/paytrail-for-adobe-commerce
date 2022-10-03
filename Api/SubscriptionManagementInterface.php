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
     * Show orders from customer subscriptions
     *
     * @return \Magento\Framework\Phrase
     */
    public function showSubscriptionOrders();
}
