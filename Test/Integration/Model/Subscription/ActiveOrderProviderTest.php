<?php

namespace Paytrail\PaymentService\Model\Subscription;

use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class ActiveOrderProviderTest extends TestCase
{
    const VALID_STATUS = 'pending_payment';
    const VALID_STATE = 'pending_payment';
    /**
     * @var ActiveOrderProvider
     */
    private $provider;
    /**
     * @var CollectionFactory
     */
    private $orderFactory;
    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Collection
     */
    private $allOrders;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->provider = $objectManager->create(ActiveOrderProvider::class);
        $this->orderFactory = $objectManager->create(CollectionFactory::class);

        parent::setUp();
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture default cataloginventory/item_options/backorders 1
     * @magentoConfigFixture default cataloginventory/item_options/use_deferred_stock_update 1
     * @magentoDataFixture Paytrail_PaymentService::Test/Integration/_files/pending_subscription.php
     * @return void
     */
    public function testGetPayableOrderIds()
    {
        $orderIds = $this->provider->getPayableOrderIds();
        $this->validateFormat($orderIds);

        foreach ($orderIds as $orderId) {
            $this->assertNotNull($orderId);
            $this->assertIsNumeric($orderId);
            $this->isOrderValid($orderId);
        }
    }

    /**
     * @param array $result
     * @return void
     */
    private function validateFormat(array $result): void
    {
        $this->assertIsArray($result);
        $this->assertNotEmpty($result, 'No Payable Order Ids returned');
    }

    private function isOrderValid($orderId)
    {
        if (!isset($this->allOrders)) {
            $this->allOrders = $this->orderFactory->create();
        }

        /** @var \Magento\Sales\Api\Data\OrderInterface $order */
        $order = $this->allOrders->getItemById($orderId);
        $this->assertEquals(
            self::VALID_STATUS,
            $order->getStatus(),
            'Returned order id points to an order with invalid status'
        );
    }
}
