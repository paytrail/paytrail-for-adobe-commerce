<?php

namespace Paytrail\PaymentService\Model\Recurring;

use Paytrail\PaymentService\Api\Data\SubscriptionInterface;
use Paytrail\PaymentService\Model\Subscription\SubscriptionLinkRepository;
use Paytrail\PaymentService\Model\ResourceModel\Subscription\SubscriptionLink\CollectionFactory;
use PHPUnit\Framework\TestCase;
use Magento\TestFramework\Helper\Bootstrap;

class NotifyTest extends TestCase
{
    const VALID_STATUSES = [
        SubscriptionInterface::STATUS_ACTIVE
    ];

    /**
     * @var mixed|Notify
     */
    private $notify;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
     */
    private $orderCollection;

    /**
     * @var CollectionFactory
     */
    private $subscriptionCollection;

    private $subscriptionLinkRepository;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->notify = $objectManager->create(\Paytrail\PaymentService\Model\Recurring\Notify::class);
        $this->orderCollection = $objectManager->create(
            \Magento\Sales\Model\ResourceModel\Order\CollectionFactory::class
        );
        $this->subscriptionCollection = $objectManager->create(CollectionFactory::class);
        $this->subscriptionLinkRepository = $objectManager->create(SubscriptionLinkRepository::class);
    }

    /**
     * @dataProvider notifyDataProvider
     * @magentoDbIsolation disabled
     * @magentoDataFixture Paytrail_PaymentService::Test/Integration/_files/customer_notify.php
     */
    public function testProcess($params, $expected)
    {
        $this->notify->process();
        $subscriptions = $this->subscriptionCollection->create();
        $orders = $this->validateOrders();
        $this->assertEquals(
            2,
            $subscriptions->getSize(),
            'Expected notify cron to result in 2 recurring payment items'
        );

        foreach ($orders as $order) {
            $this->subscriptionLinkRepository->linkOrderToSubscription($order->getId(),$params['subscription_id']);
            $subscription = $this->subscriptionLinkRepository->getSubscriptionIdFromOrderId($params['order_id']);
            $this->assertEquals(
                $expected['subscription_id'],
                $subscription,
                'Subscriptions value must match'
            );
        }

        /**
         * Validate generated recurring payments.
         *
         * @var SubscriptionInterface $subscription
         */
        foreach ($subscriptions as $subscription) {
            $this->assertContains(
                $subscription->getStatus(),
                self::VALID_STATUSES,
                sprintf(
                    'Generated recurring payment status must be one of %s',
                    implode(', ', self::VALID_STATUSES)
                )
            );
        }
    }

    /**
     * @return \Magento\Sales\Model\ResourceModel\Order\Collection
     */
    private function validateOrders(): \Magento\Sales\Model\ResourceModel\Order\Collection
    {
        /** @var \Magento\Sales\Model\ResourceModel\Order\Collection $orders */
        $orders = $this->orderCollection->create();
        $orders->addOrder('entity_id', 'ASC');
        $this->assertEquals(
            2,
            $orders->getSize(),
            'Expected Notify cron to create one additional order'
        );

        $firstOrder = $orders->getFirstItem();
        /** @var \Magento\Sales\Model\Order $order */
        foreach ($orders as $order) {
            $this->assertEquals(
                $firstOrder->getGrandTotal(),
                $order->getGrandTotal(),
                'Order totals must match'
            );

            if ($order->getId() !== $firstOrder->getId()) {
                $this->assertContains(
                    $order->getState(),
                    [
                        \Magento\Sales\Model\Order::STATE_NEW,
                        \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT,
                    ],
                    'Order state Invalid'
                );
            }
        }

        return $orders;
    }

    public function notifyDataProvider()
    {
        return [
            'Save fails without recurring profile' => [
                'params' => [
                    'order_id' => '2',
                    'subscription_id' => '1'
                ],
                'expected' => [
                    'subscription_id' => '1',
                ]
            ]
        ];
    }
}
