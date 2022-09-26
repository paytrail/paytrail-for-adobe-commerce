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
     * @return mixed
     */
    public function cancelSubscription(string $subscriptionId);
}
