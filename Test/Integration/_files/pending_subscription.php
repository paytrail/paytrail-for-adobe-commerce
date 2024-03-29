<?php
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;
use Magento\Sales\Api\Data\OrderInterfaceFactory;
use Magento\TestFramework\Helper\Bootstrap;

// Please note that the two orders generated by these fixtures have differing totals. If you need monetary validation
// in another implementation you'll need a different fixture.
Resolver::getInstance()->requireDataFixture('Magento/Sales/_files/order_pending_payment.php');
Resolver::getInstance()->requireDataFixture('Paytrail_PaymentService::Test/Integration/_files/customer_notify.php');


$objectManager = Bootstrap::getObjectManager();
/** @var \Magento\Sales\Model\Order $order */
$subscriptions = $objectManager->get(\Paytrail\PaymentService\Model\ResourceModel\Subscription\CollectionFactory::class)->create();

$order = $objectManager->get(OrderInterfaceFactory::class)->create()->loadByIncrementId('100000001');
$link = $objectManager->create(\Paytrail\PaymentService\Api\Data\SubscriptionLinkInterface::class);
$link->setOrderId($order->getId());
$link->setSubscriptionId($subscriptions->getFirstItem()->getId());
$link->save();