<?php
namespace Paytrail\PaymentService\Plugin\Api;

use Magento\Framework\Exception\NoSuchEntityException;
use Paytrail\PaymentService\Api\Data\SubscriptionInterface;

class OrderRepository
{
    /** @var \Paytrail\PaymentService\Api\SubscriptionRepositoryInterface */
    private $subscriptionRepository;

    /** @var \Magento\Sales\Api\Data\OrderExtensionFactory */
    private $orderExtensionFactory;

    /** @var \Paytrail\PaymentService\Model\Subscription\SubscriptionLinkRepository */
    private $subscriptionLinkRepository;

    /**
     * @param \Paytrail\PaymentService\Api\SubscriptionRepositoryInterface $subscriptionRepository
     * @param \Magento\Sales\Api\Data\OrderExtensionFactory $extensionFactory
     * @param \Paytrail\PaymentService\Model\Subscription\SubscriptionLinkRepository $subscriptionLinkRepository
     */
    public function __construct(
        \Paytrail\PaymentService\Api\SubscriptionRepositoryInterface               $subscriptionRepository,
        \Magento\Sales\Api\Data\OrderExtensionFactory                  $extensionFactory,
        \Paytrail\PaymentService\Model\Subscription\SubscriptionLinkRepository $subscriptionLinkRepository
    ) {
        $this->subscriptionRepository = $subscriptionRepository;
        $this->orderExtensionFactory = $extensionFactory;
        $this->subscriptionLinkRepository = $subscriptionLinkRepository;
    }

    /**
     * @param \Magento\Sales\Api\OrderRepositoryInterface $subject
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return \Magento\Sales\Api\Data\OrderInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function afterSave(
        \Magento\Sales\Api\OrderRepositoryInterface $subject,
        $order
    ) {
        $extensionAttributes = $order->getExtensionAttributes();
        if ($extensionAttributes && $extensionAttributes->getRecurringPayment()) {
            $extensionAttributes->getRecurringPayment()->setOrderId($order->getId());
            $this->subscriptionRepository->save($extensionAttributes->getRecurringPayment());
        }

        return $order;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return SubscriptionInterface|bool
     */
    private function getRecurringPayment(\Magento\Sales\Api\Data\OrderInterface $order)
    {
        try {
            $payment = $this->subscriptionLinkRepository->getSubscriptionFromOrderId($order->getId());
        } catch (NoSuchEntityException $e) {
            $payment = false;
        }

        return $payment;
    }
}
