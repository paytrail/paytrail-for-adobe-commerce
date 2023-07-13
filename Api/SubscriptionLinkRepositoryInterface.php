<?php

namespace Paytrail\PaymentService\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

interface SubscriptionLinkRepositoryInterface
{
    /**
     * @param $linkId
     * @return Data\SubscriptionLinkInterface
     * @throws NoSuchEntityException
     */
    public function get($linkId);

    /**
     * @param Data\SubscriptionLinkInterface $subscriptionLink
     * @return Data\SubscriptionLinkInterface
     * @throws CouldNotSaveException
     */
    public function save(Data\SubscriptionLinkInterface $subscriptionLink);

    /**
     * @param Data\SubscriptionLinkInterface $subscriptionLink
     * @throws CouldNotDeleteException
     */
    public function delete(Data\SubscriptionLinkInterface $subscriptionLink);

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return Data\SubscriptionLinkSearchResultInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): Data\SubscriptionLinkSearchResultInterface;

    /**
     * @param $orderId
     * @return mixed
     */
    public function getSubscriptionFromOrderId($orderId);

    /**
     * @param $orderId
     * @param $subscriptionId
     * @return mixed
     */
    public function linkOrderToSubscription($orderId, $subscriptionId);

    /**
     * @param $subscriptionId
     * @return mixed
     */
    public function getOrderIdsBySubscriptionId($subscriptionId);
}
