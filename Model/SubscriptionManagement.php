<?php

namespace Paytrail\PaymentService\Model;

use Exception;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Paytrail\PaymentService\Api\PaymentTokenRepositoryInterface;
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
     * @var PaymentTokenRepositoryInterface
     */
    private PaymentTokenRepositoryInterface $paymentTokenRepository;

    /**
     * @var UserContextInterface
     */
    private UserContextInterface $userContext;

    /**
     * @param Session $customerSession
     * @param SubscriptionRepositoryInterface $subscriptionRepository
     * @param SubscriptionLinkRepositoryInterface $subscriptionLinkRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderManagementInterface $orderManagementInterface
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param LoggerInterface $logger
     * @param PaymentTokenRepositoryInterface $paymentTokenRepository
     * @param UserContextInterface $userContext
     */
    public function __construct(
        Session $customerSession,
        SubscriptionRepositoryInterface $subscriptionRepository,
        SubscriptionLinkRepositoryInterface $subscriptionLinkRepository,
        OrderRepositoryInterface $orderRepository,
        OrderManagementInterface $orderManagementInterface,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        LoggerInterface $logger,
        PaymentTokenRepositoryInterface $paymentTokenRepository,
        UserContextInterface $userContext
    ) {
        $this->customerSession = $customerSession;
        $this->subscriptionRepository = $subscriptionRepository;
        $this->subscriptionLinkRepository = $subscriptionLinkRepository;
        $this->orderRepository = $orderRepository;
        $this->orderManagementInterface = $orderManagementInterface;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->logger = $logger;
        $this->paymentTokenRepository = $paymentTokenRepository;
        $this->userContext = $userContext;
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
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            throw new LocalizedException(__("Subscription couldn't be canceled"));
        }

        return __('Subscription has been canceled correctly');
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

        $this->paymentTokenRepository->validateTokensCustomer($paymentToken, $customerId);
        $this->subscriptionRepository->validateSubscriptionsCustomer($subscription, $customerId);

        $this->subscriptionRepository->updateSubscriptionsToken($subscription, $paymentToken);

        return true;
    }
}
