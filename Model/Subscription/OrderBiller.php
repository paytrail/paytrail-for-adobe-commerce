<?php

namespace Paytrail\PaymentService\Model\Subscription;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\OrderRepository;
use Paytrail\PaymentService\Api\SubscriptionLinkRepositoryInterface;
use Paytrail\PaymentService\Api\SubscriptionRepositoryInterface;
use Paytrail\PaymentService\Helper\Data;
use Paytrail\PaymentService\Model\ResourceModel\Subscription as SubscriptionResource;
use Paytrail\PaymentService\Model\ResourceModel\Subscription\CollectionFactory;
use Paytrail\PaymentService\Model\ResourceModel\VaultPaymentToken;
use Paytrail\PaymentService\Model\Subscription;
use Paytrail\PaymentService\Model\Token\Payment;
use Psr\Log\LoggerInterface;
use \Paytrail\PaymentService\Model\ResourceModel\Subscription\Collection;

class OrderBiller
{
    /**
     * @var PaymentCount
     */
    private $paymentCount;

    /**
     * @var Payment
     */
    private $mitPayment;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var NextDateCalculator
     */
    private $nextDateCalculator;

    /**
     * @var SubscriptionRepositoryInterface
     */
    private $subscriptionRepository;

    /**
     * @var SubscriptionResource
     */
    private $subscriptionResource;

    /**
     * @var SubscriptionLinkRepositoryInterface
     */
    private $subscriptionLinkRepository;

    /**
     * @var OrderSender
     */
    private $orderSender;

    /**
     * @var Order
     */
    private $orderRepository;

    /**
     * @param PaymentCount $paymentCount
     * @param Payment $mitPayment
     * @param CollectionFactory $collectionFactory
     * @param NextDateCalculator $nextDateCalculator
     * @param SubscriptionRepositoryInterface $subscriptionRepository
     * @param SubscriptionResource $subscriptionResource
     * @param LoggerInterface $logger
     * @param SubscriptionLinkRepositoryInterface $subscriptionLinkRepository
     * @param OrderSender $orderSender
     * @param OrderRepository $orderRepository
     */
    public function __construct(
        PaymentCount                    $paymentCount,
        Payment                         $mitPayment,
        CollectionFactory               $collectionFactory,
        NextDateCalculator              $nextDateCalculator,
        SubscriptionRepositoryInterface $subscriptionRepository,
        SubscriptionResource            $subscriptionResource,
        LoggerInterface                 $logger,
        SubscriptionLinkRepositoryInterface $subscriptionLinkRepository,
        OrderSender $orderSender,
        OrderRepository $orderRepository
    ) {
        $this->paymentCount = $paymentCount;
        $this->mitPayment = $mitPayment;
        $this->collectionFactory = $collectionFactory;
        $this->nextDateCalculator = $nextDateCalculator;
        $this->subscriptionRepository = $subscriptionRepository;
        $this->subscriptionResource = $subscriptionResource;
        $this->logger = $logger;
        $this->subscriptionLinkRepository = $subscriptionLinkRepository;
        $this->orderSender = $orderSender;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @param int[] $orderIds
     */
    public function billOrdersById($orderIds)
    {
        /** @var Collection $subscriptionsToCharge */
        $subscriptionsToCharge = $this->collectionFactory->create();
        $subscriptionsToCharge->getBillingCollectionByOrderIds($orderIds);

        /** @var Subscription $subscription */
        foreach ($subscriptionsToCharge as $subscription) {
            if (!$this->validateToken($subscription)) {
                continue;
            }

            $paymentSuccess = $this->createMitPayment($subscription);
            if (!$paymentSuccess) {
                $this->paymentCount->reduceFailureRetryCount($subscription);
                continue;
            }
            $this->sendOrderConfirmationEmail($subscription->getId());
            $this->updateNextOrderDate($subscription);
        }
    }

    /**
     * @param $subscriptionId
     * @return void
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function sendOrderConfirmationEmail($subscriptionId)
    {
        $orderIds = $this->subscriptionLinkRepository->getOrderIdsBySubscriptionId($subscriptionId);
        $this->orderSender->send($this->orderRepository->get(array_last($orderIds)));
    }

    /**
     * @param Subscription $subscription
     * @return bool
     */
    private function validateToken($subscription)
    {
        $valid = true;
        if (!$subscription->getData('token_active') || !$subscription->getData('token_visible')) {
            $this->logger->warning(\__(
                'Unable to charge subscription id: %id token is invalid',
                ['id' => $subscription->getId()]
            ));
            $this->paymentCount->reduceFailureRetryCount($subscription);

            $valid = false;
        }

        return $valid;
    }

    /**
     * @param Subscription $subscription Must include order id of the subscription and public hash of the vault token.
     * @return bool
     * For subscription param @see Collection::getBillingCollectionByOrderIds
     */
    private function createMitPayment($subscription): bool
    {
        $paymentSuccess = false;
        try {
            $paymentSuccess = $this->mitPayment->makeMitPayment(
                $subscription->getData('order_id'),
                $subscription->getData('token')
            );
        } catch (LocalizedException $e) {
            $this->logger->error(\__(
                'Recurring Payment: Unable to create a charge to customer token error: %error',
                ['error' => $e->getMessage()]
            ));
        }
        return $paymentSuccess;
    }

    private function updateNextOrderDate(Subscription $subscription)
    {
        $subscription->setNextOrderDate(
            $this->nextDateCalculator->getNextDate(
                $subscription->getRecurringProfileId(),
                $subscription->getNextOrderDate()
            )
        );

        $this->saveSubscription($subscription);
    }

    /**
     * @param Subscription $subscription
     * @return void
     */
    private function saveSubscription(Subscription $subscription): void
    {
        try {
            $this->subscriptionRepository->save($subscription);
        } catch (CouldNotSaveException $e) {
            $this->logger->critical(\__(
                'Recurring payment: Cancelling subscription %id, unable to update subscription\'s next order date: %error',
                [
                    'id' => $subscription->getId(),
                    'error' => $e->getMessage()
                ]
            ));

            // Prevent subscription from being rebilled over and over again
            // if for some reason the subscription is unable to be saved.
            $this->subscriptionResource->forceFailedStatus($subscription->getId());
        }
    }
}
