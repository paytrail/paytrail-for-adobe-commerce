<?php
namespace Paytrail\PaymentService\Model;

use Magento\Customer\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Paytrail\PaymentService\Api\SubscriptionLinkRepositoryInterface;
use Paytrail\PaymentService\Api\SubscriptionRepositoryInterface;
use Psr\Log\LoggerInterface;

class SubscriptionManagement
{
    protected const STATUS_CLOSED = 'closed';
    protected const ORDER_PENDING_STATUS = 'pending';

    /**
     * @var Session
     */
    protected $customerSession;

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
     * @param Session $customerSession
     * @param SubscriptionRepositoryInterface $subscriptionRepository
     * @param SubscriptionLinkRepositoryInterface $subscriptionLinkRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderManagementInterface $orderManagementInterface
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param LoggerInterface $logger
     */
    public function __construct(
        Session $customerSession,
        SubscriptionRepositoryInterface $subscriptionRepository,
        SubscriptionLinkRepositoryInterface $subscriptionLinkRepository,
        OrderRepositoryInterface $orderRepository,
        OrderManagementInterface $orderManagementInterface,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        LoggerInterface $logger
    ) {
        $this->customerSession = $customerSession;
        $this->subscriptionRepository = $subscriptionRepository;
        $this->subscriptionLinkRepository = $subscriptionLinkRepository;
        $this->orderRepository = $orderRepository;
        $this->orderManagementInterface = $orderManagementInterface;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->logger = $logger;
    }

    /**
     * @param $subscriptionId
     * @return \Magento\Framework\Phrase
     * @throws LocalizedException
     */
    public function cancelSubscription($subscriptionId)
    {
        try {
            $subscription = $this->subscriptionRepository->get((int)$subscriptionId);
            $customer = $this->customerSession->getCustomer();

            $orderIds = $this->subscriptionLinkRepository->getOrderIdsBySubscriptionId((int)$subscriptionId);
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('entity_id', $orderIds, 'in')
                ->create();
            $orders = $this->orderRepository->getList($searchCriteria);

            foreach ($orders->getItems() as $order) {
                if (!$customer->getId() || $customer->getId() != $order->getCustomerId()) {
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
     * @return array[]
     * @throws LocalizedException
     */
    public function showSubscriptionOrders(): array
    {
        $subscriptionResult = [];
        try {
            if ($this->customerSession->isLoggedIn()) {
                $customerId = $this->customerSession->getCustomerId();
                $searchCriteria = $this->searchCriteriaBuilder
                    ->addFilter('customer_id',$customerId,'in')
                    ->create();
                $subscriptions = $this->subscriptionRepository->getList($searchCriteria);

                foreach ($subscriptions as $subscription) {
                    $subscriptionResult[] = [
                        'subscription_id' => $subscription->getId(),
                        'status' => $subscription->getStatus(),
                        'next_order_date' => $subscription->getNextOrderDate(),
                        'recurring_profile_id' => $subscription->getRecurringProfileId(),
                        'updated_at' => $subscription->getUpdatedAt(),
                        'repeat_count_left' => $subscription->getRepeatCountLeft(),
                        'retry_count' => $subscription->getRetryCount(),
                        'selected_token' => $subscription->getSelectedToken()
                    ];
                }
            }

            return $subscriptionResult;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new LocalizedException(__("Subscription orders can't be shown"));
        }
    }
}
