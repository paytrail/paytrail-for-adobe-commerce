<?php

namespace Paytrail\PaymentService\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Paytrail\PaymentService\Api\Data\SubscriptionInterface;
use Paytrail\PaymentService\Api\Data\SubscriptionSearchResultInterface;

interface SubscriptionRepositoryInterface
{
    /**
     * Get subscription.
     *
     * @param int $entityId
     * @return SubscriptionInterface
     *
     * @throws NoSuchEntityException
     */
    public function get(int $entityId): SubscriptionInterface;

    /**
     * Save subscription.
     *
     * @param SubscriptionInterface $subscription
     * @return SubscriptionInterface
     *
     * @throws CouldNotSaveException
     */
    public function save(SubscriptionInterface $subscription): SubscriptionInterface;

    /**
     * Get list of subscriptions.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return \Paytrail\PaymentService\Api\Data\SubscriptionSearchResultInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SubscriptionSearchResultInterface;

    /**
     * Delete subscription.
     *
     * @param SubscriptionInterface $subscription
     * @return void
     *
     * @throws CouldNotDeleteException
     */
    public function delete(SubscriptionInterface $subscription);
}
