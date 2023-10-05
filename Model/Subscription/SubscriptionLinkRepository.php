<?php

namespace Paytrail\PaymentService\Model\Subscription;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Paytrail\PaymentService\Api\Data;
use Paytrail\PaymentService\Api\Data\SubscriptionInterface;
use Paytrail\PaymentService\Api\Data\SubscriptionInterfaceFactory;
use Paytrail\PaymentService\Api\Data\SubscriptionLinkInterfaceFactory;
use Paytrail\PaymentService\Api\Data\SubscriptionLinkSearchResultInterfaceFactory;
use Paytrail\PaymentService\Api\SubscriptionLinkRepositoryInterface;
use Paytrail\PaymentService\Model\ResourceModel\Subscription\SubscriptionLink;
use Paytrail\PaymentService\Model\ResourceModel\Subscription\SubscriptionLink\CollectionFactory;
use Paytrail\PaymentService\Model\Subscription;
use Paytrail\PaymentService\Model\ResourceModel\Subscription as SubscriptionResource;

class SubscriptionLinkRepository implements SubscriptionLinkRepositoryInterface
{
    /**
     * @var SubscriptionLink
     */
    private $subscriptionLinkResource;

    /**
     * @var SubscriptionLinkInterfaceFactory
     */
    private $subscriptionLinkFactory;

    /**
     * @var SubscriptionLink
     */
    private $subscriptionLinkResultFactory;

    /**
     * @var CollectionProcessorInterface
     */
    private $collectionProcessor;

    /**
     * @var MessageManagerInterface
     */
    private $messageManager;

    /**
     * @var SubscriptionResource
     */
    private $subscriptionResource;

    /**
     * @var SubscriptionInterfaceFactory
     */
    private $subscriptionFactory;

    /**
     * @var CollectionFactory
     */
    private $subscriptionLinkCollectionFactory;

    /**
     * @param SubscriptionLink $subscriptionLinkResource
     * @param SubscriptionLinkInterfaceFactory $subscriptionLinkFactory
     * @param SubscriptionLinkSearchResultInterfaceFactory $subscriptionLinkResultFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param MessageManagerInterface $messageManager
     * @param SubscriptionResource $subscriptionResource
     * @param SubscriptionInterfaceFactory $subscriptionFactory
     * @param CollectionFactory $subscriptionLinkCollectionFactory
     */
    public function __construct(
        SubscriptionLink                             $subscriptionLinkResource,
        SubscriptionLinkInterfaceFactory             $subscriptionLinkFactory,
        SubscriptionLinkSearchResultInterfaceFactory $subscriptionLinkResultFactory,
        CollectionProcessorInterface                 $collectionProcessor,
        MessageManagerInterface                      $messageManager,
        SubscriptionResource                         $subscriptionResource,
        SubscriptionInterfaceFactory                 $subscriptionFactory,
        CollectionFactory                            $subscriptionLinkCollectionFactory
    ) {
        $this->subscriptionLinkResource = $subscriptionLinkResource;
        $this->subscriptionLinkFactory = $subscriptionLinkFactory;
        $this->subscriptionLinkResultFactory = $subscriptionLinkResultFactory;
        $this->collectionProcessor = $collectionProcessor;
        $this->messageManager = $messageManager;
        $this->subscriptionResource = $subscriptionResource;
        $this->subscriptionFactory = $subscriptionFactory;
        $this->subscriptionLinkCollectionFactory = $subscriptionLinkCollectionFactory;
    }

    /**
     * @inheritDoc
     */
    public function get($linkId)
    {
        /** @var Subscription $subscription */
        $subscription = $this->subscriptionLinkFactory->create();
        $this->subscriptionLinkResource->load($subscription, $linkId);

        if (!$subscription->getId()) {
            throw new NoSuchEntityException(\__(
                'No subscription link found with id %id',
                [
                    'id' => $linkId
                ]
            ));
        }

        return $subscription;
    }

    /**
     * @inheritDoc
     */
    public function save(Data\SubscriptionLinkInterface $subscriptionLink)
    {
        try {
            $this->subscriptionLinkResource->save($subscriptionLink);
        } catch (\Throwable $e) {
            throw new CouldNotSaveException(\__(
                'Could not save Recurring Profile: %error',
                ['error' => $e->getMessage()]
            ));
        }

        return $subscriptionLink;
    }

    /**
     * @inheritDoc
     */
    public function delete(Data\SubscriptionLinkInterface $subscriptionLink)
    {
        try {
            $this->subscriptionLinkResource->delete($subscriptionLink);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__(
                'Unable to delete subscription link: %error',
                ['error' => $e->getMessage()]
            ));
        }
    }

    /**
     * @inheritDoc
     */
    public function getList(
        \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
    ) : Data\SubscriptionLinkSearchResultInterface {
        /** @var Data\RecurringProfileSearchResultInterface $searchResult */
        $searchResult = $this->subscriptionLinkResultFactory->create();
        $this->collectionProcessor->process($searchCriteria, $searchResult);
        $searchResult->setSearchCriteria($searchCriteria);

        return $searchResult;
    }

    /**
     * @param $orderId
     * @return SubscriptionInterface
     */
    public function getSubscriptionFromOrderId($orderId): SubscriptionInterface
    {
        $subscriptionId = $this->getSubscriptionIdFromOrderId($orderId);
        $subscription = $this->subscriptionFactory->create();
        $this->subscriptionResource->load($subscription, $subscriptionId, 'entity_id');

        return $subscription;
    }

    /**
     * @param $orderId
     * @param $subscriptionId
     * @return mixed|void
     */
    public function linkOrderToSubscription($orderId, $subscriptionId)
    {
        try {
            $subscriptionLink = $this->subscriptionLinkFactory->create();
            $subscriptionLink->setOrderId($orderId);
            $subscriptionLink->setSubscriptionId($subscriptionId);
            $this->save($subscriptionLink);
        }
        catch (CouldNotSaveException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
    }

    /**
     * @param $orderId
     * @return string
     */
    public function getSubscriptionIdFromOrderId($orderId)
    {
        $subscription = $this->subscriptionLinkFactory->create();
        $this->subscriptionLinkResource->load($subscription, $orderId, 'order_id');

        return $subscription->getSubscriptionId();
    }

    /**
     * @param $subscriptionId
     * @return array
     */
    public function getOrderIdsBySubscriptionId($subscriptionId): array
    {
        $collection = $this->subscriptionLinkCollectionFactory->create();
        $collection->addFieldToFilter(
            'subscription_id',['eq' => $subscriptionId]
        );

        $subscriptionLinks = [];
        foreach ($collection->getItems() as $item) {
            $subscriptionLinks[] = $item->getOrderId();
        }

        return $subscriptionLinks;
    }


}
