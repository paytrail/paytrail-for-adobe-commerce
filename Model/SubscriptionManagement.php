<?php

namespace Paytrail\PaymentService\Model;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\FilterFactory;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Paytrail\PaymentService\Api\Data\SubscriptionSearchResultInterface;
use Paytrail\PaymentService\Api\SubscriptionLinkRepositoryInterface;
use Paytrail\PaymentService\Api\SubscriptionManagementInterface;
use Paytrail\PaymentService\Api\SubscriptionRepositoryInterface;
use Psr\Log\LoggerInterface;

class SubscriptionManagement implements SubscriptionManagementInterface
{
    protected const STATUS_CLOSED = 'closed';
    protected const ORDER_PENDING_STATUS = 'pending';

    /**
     * @var \Magento\Authorization\Model\UserContextInterface
     */
    protected $userContext;

    /**
     * @var SubscriptionRepositoryInterface
     */
    protected $subscriptionRepository;

    /**
     * @var SubscriptionLinkRepositoryInterface
     */
    protected $subscriptionLinkRepository;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var OrderManagementInterface
     */
    protected $orderManagementInterface;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var FilterBuilder
     */
    protected $filterBuilder;

    /**
     * @var FilterGroupBuilder
     */
    protected $groupBuilder;

    /**
     * @var UserContextInterface
     */
    private $userContext;

    /**
     * @param SubscriptionRepositoryInterface $subscriptionRepository
     * @param SubscriptionLinkRepositoryInterface $subscriptionLinkRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderManagementInterface $orderManagementInterface
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     * @param LoggerInterface $logger
     */
    public function __construct(
        \Magento\Authorization\Model\UserContextInterface $userContext,
        SubscriptionRepositoryInterface $subscriptionRepository,
        SubscriptionLinkRepositoryInterface $subscriptionLinkRepository,
        OrderRepositoryInterface $orderRepository,
        OrderManagementInterface $orderManagementInterface,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder,
        FilterGroupBuilder $filterGroupBuilder,
        LoggerInterface $logger
        LoggerInterface $logger,
        UserContextInterface $userContext
    ) {
        $this->userContext = $userContext;
        $this->subscriptionRepository = $subscriptionRepository;
        $this->subscriptionLinkRepository = $subscriptionLinkRepository;
        $this->orderRepository = $orderRepository;
        $this->orderManagementInterface = $orderManagementInterface;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->groupBuilder = $filterGroupBuilder;
        $this->logger = $logger;
    }

    /**
     * @param $subscriptionId
     * @return \Magento\Framework\Phrase
     * @throws LocalizedException
     */
    public function cancelSubscription($subscriptionId)
    {
        $customerId = $this->userContext->getUserId();
        if (!$customerId) {
            throw new LocalizedException(__('Customer is not authorized for this operation'));
        }

        try {
            $subscription = $this->subscriptionRepository->get((int)$subscriptionId);
            $customerId = $this->userContext->getUserId();

            $orderIds = $this->subscriptionLinkRepository->getOrderIdsBySubscriptionId((int)$subscriptionId);
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('entity_id', $orderIds, 'in')
                ->create();
            $orders = $this->orderRepository->getList($searchCriteria);

            foreach ($orders->getItems() as $order) {
                if (!$customerId || $customerId != $order->getCustomerId()) {
                if ($customerId != $order->getCustomerId()) {
                    throw new LocalizedException(__('Customer is not authorized for this operation'));
                }
                $subscription->setStatus(self::STATUS_CLOSED);
                if ($order->getStatus() === Order::STATE_PENDING_PAYMENT
                    || $order->getStatus() === self::ORDER_PENDING_STATUS) {
                    $this->orderManagementInterface->cancel($order->getId());
                }
            }

            $this->subscriptionRepository->save($subscription);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new LocalizedException(__("Subscription couldn't be canceled"));
        }

        return __('Subscription has been canceled correctly');
    }

    /**
     * @return \Paytrail\PaymentService\Api\Data\SubscriptionSearchResultInterface
     * @throws LocalizedException
     */
    public function showSubscriptionOrders(SearchCriteriaInterface $searchCriteria): SubscriptionSearchResultInterface
    {
        try {
            if ($this->userContext->getUserId()) {
                $this->filterByCustomer($searchCriteria);

                return $this->subscriptionRepository->getList($searchCriteria);
            }
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            throw new LocalizedException(__("Subscription orders can't be shown"));
        }

        throw new LocalizedException(__("Customer is not logged in"));
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return void
     */
    private function filterByCustomer(SearchCriteriaInterface $searchCriteria): void
    {
        $customerFilter = $this->filterBuilder
            ->setField('customer_id')
            ->setValue($this->userContext->getUserId())
            ->setConditionType('eq')
            ->create();
        $customerFilterGroup = $this->groupBuilder->addFilter($customerFilter)->create();
        $groups = $searchCriteria->getFilterGroups();
        $groups[] = $customerFilterGroup;
        $searchCriteria->setFilterGroups($groups);
    }
}
