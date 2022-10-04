<?php
namespace Paytrail\PaymentService\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Paytrail\PaymentService\Api\Data\SubscriptionInterface;
use Paytrail\PaymentService\Api\Data\SubscriptionSearchResultInterface;

interface SubscriptionRepositoryInterface
{
    /**
     * @param int $entityId
     * @return SubscriptionInterface
     *
     * @throws NoSuchEntityException
     */
    public function get(int $entityId): SubscriptionInterface;

    /**
     * @param SubscriptionInterface $subscription
     * @param int $customerId
     * @return void
     *
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function validateSubscriptionsCustomer(SubscriptionInterface $subscription, int $customerId): void;

    /**
     * @param SubscriptionInterface $subscription
     * @param PaymentTokenInterface $paymentToken
     * @return void
     *
     * @throws CouldNotSaveException
     */
    public function updateSubscriptionsToken(SubscriptionInterface $subscription, PaymentTokenInterface $paymentToken): void;

    /**
     * @param SubscriptionInterface $subscription
     * @return SubscriptionInterface
     *
     * @throws CouldNotSaveException
     */
    public function save(SubscriptionInterface $subscription): SubscriptionInterface;

    /**
     * @param $searchCriteria
     * @return \Paytrail\PaymentService\Model\ResourceModel\Subscription\Collection
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SubscriptionSearchResultInterface;

    /**
     * @param SubscriptionInterface $subscription
     * @return void
     *
     * @throws CouldNotDeleteException
     */
    public function delete(SubscriptionInterface $subscription);
}
