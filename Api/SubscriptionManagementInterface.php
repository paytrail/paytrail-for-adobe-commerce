<?php
namespace Paytrail\PaymentService\Api;

use Magento\Framework\Api\SearchCriteriaInterface;

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
     * Shows customer subscriptions
     *
     * @return \Paytrail\PaymentService\Api\Data\SubscriptionSearchResultInterface
     */
    public function showSubscriptions(SearchCriteriaInterface $searchCriteria);
}
