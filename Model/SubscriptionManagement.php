<?php

namespace Paytrail\PaymentService\Model;

use Exception;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Paytrail\PaymentService\Api\Data\SubscriptionInterface;
use Paytrail\PaymentService\Api\SubscriptionLinkRepositoryInterface;
use Paytrail\PaymentService\Api\SubscriptionManagementInterface;
use Paytrail\PaymentService\Api\SubscriptionRepositoryInterface;
use Paytrail\PaymentService\Model\Api\ShowSubscriptionsDataProvider;
use Paytrail\PaymentService\Model\Validation\CustomerData;
use Psr\Log\LoggerInterface;

class SubscriptionManagement implements SubscriptionManagementInterface
{
    protected const STATUS_CLOSED = 'closed';
    protected const ORDER_PENDING_STATUS = 'pending';

    /**
     * @var UserContextInterface
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
     * @var PaymentTokenRepositoryInterface
     */
    private PaymentTokenRepositoryInterface $paymentTokenRepository;

    /**
     * @var CustomerData
     */
    private $customerData;

    /**
     * @var ShowSubscriptionsDataProvider
     */
    private $showSubscriptionsDataProvider;

    /**
     * @param UserContextInterface $userContext
     * @param SubscriptionRepositoryInterface $subscriptionRepository
     * @param SubscriptionLinkRepositoryInterface $subscriptionLinkRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderManagementInterface $orderManagementInterface
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param LoggerInterface $logger
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     * @param PaymentTokenRepositoryInterface $paymentTokenRepository
     * @param CustomerData $customerData
     * @param ShowSubscriptionsDataProvider $showSubscriptionsDataProvider
     */
    public function __construct(
        UserContextInterface                $userContext,
        SubscriptionRepositoryInterface     $subscriptionRepository,
        SubscriptionLinkRepositoryInterface $subscriptionLinkRepository,
        OrderRepositoryInterface            $orderRepository,
        OrderManagementInterface            $orderManagementInterface,
        SearchCriteriaBuilder               $searchCriteriaBuilder,
        LoggerInterface                     $logger,
        FilterBuilder                       $filterBuilder,
        FilterGroupBuilder                  $filterGroupBuilder,
        PaymentTokenRepositoryInterface     $paymentTokenRepository,
        CustomerData                        $customerData,
        ShowSubscriptionsDataProvider $showSubscriptionsDataProvider
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
        $this->paymentTokenRepository = $paymentTokenRepository;
        $this->customerData = $customerData;
        $this->showSubscriptionsDataProvider = $showSubscriptionsDataProvider;
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
            if ($subscription->getStatus() === self::STATUS_CLOSED) {
                return (__('Subscription is closed'));
            }

            $customerId = $this->userContext->getUserId();
            $orderIds = $this->subscriptionLinkRepository->getOrderIdsBySubscriptionId((int)$subscriptionId);
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('entity_id', $orderIds, 'in')
                ->create();
            $orders = $this->orderRepository->getList($searchCriteria);

            foreach ($orders->getItems() as $order) {
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
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            throw new LocalizedException(__("Subscription couldn't be canceled"));
        }

        return __('Subscription has been canceled correctly');
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return array
     * @throws LocalizedException
     */
    public function showSubscriptions(SearchCriteriaInterface $searchCriteria): array
    {
        $subscriptions = [];
        try {
            if ($this->userContext->getUserId()) {
                $this->filterByCustomer($searchCriteria);
                $subscriptionCollection = $this->subscriptionRepository->getList($searchCriteria)->getItems();
                $paymentToken = $this->showSubscriptionsDataProvider->getMaskedCCById($searchCriteria);

                foreach ($subscriptionCollection as $subscription) {
                    $orderData = $this->showSubscriptionsDataProvider
                        ->getOrderDataFromSubscriptionId($subscription->getId());
                    $subscriptions[] = [
                        'entity_id' => $subscription->getId(),
                        'customer_id' => $subscription->getCustomerId(),
                        'status' => $subscription->getStatus(),
                        'next_order_date' => $subscription->getNextOrderDate(),
                        'recurring_profile_id' => $subscription->getRecurringProfileId(),
                        'updated_at' => $subscription->getUpdatedAt(),
                        'repeat_count_left' => $subscription->getRepeatCountLeft(),
                        'retry_count' => $subscription->getRetryCount(),
                        'selected_token' => $subscription->getSelectedToken(),
                        'masked_cc' => $paymentToken[$subscription->getSelectedToken()],
                        'grand_total' => $orderData['grand_total'],
                        'last_order_increment_id' => $orderData['increment_id']
                    ];
                }

                return $subscriptions;
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

    /**
     * Change assigned card for subscription
     *
     * @param string $subscriptionId
     * @param string $cardId
     *
     * @return bool
     *
     * @throws LocalizedException
     */
    public function changeSubscription(string $subscriptionId, string $cardId): bool
    {
        $paymentToken = $this->paymentTokenRepository->getById((int)$cardId);
        $subscription = $this->subscriptionRepository->get((int)$subscriptionId);

        $customerId = (int)$this->userContext->getUserId();

        $this->customerData->validateTokensCustomer($paymentToken, $customerId);
        $this->customerData->validateSubscriptionsCustomer($subscription, $customerId);

        $subscription->setSelectedToken($paymentToken->getEntityId());

        return $this->save($subscription);
    }

    /**
     * @param SubscriptionInterface $subscription
     * @return bool
     */
    private function save(SubscriptionInterface $subscription): bool
    {
        try {
            $this->subscriptionRepository->save($subscription);
        } catch (CouldNotSaveException $e) {
            return false;
        }

        return true;
    }
}
