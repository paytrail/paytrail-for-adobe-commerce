<?php

use Magento\Sales\Api\Data\OrderInterfaceFactory;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/Sales/_files/customer_order_with_simple_product.php');

$objectManager = Bootstrap::getObjectManager();
$productRepository = $objectManager->create(\Magento\Catalog\Api\ProductRepositoryInterface::class);
$productRepository->save($productRepository->get('simple-1')->setStockData(['qty' => 200]));


$subscriptionRepository = $objectManager->create(\Paytrail\PaymentService\Api\SubscriptionRepositoryInterface::class);
$profileRepo = $objectManager->create(\Paytrail\PaymentService\Api\RecurringProfileRepositoryInterface::class);
$profile = $objectManager->create(\Paytrail\PaymentService\Api\Data\RecurringProfileInterface::class);
$payment = $objectManager->create(\Paytrail\PaymentService\Api\Data\SubscriptionInterface::class);
$subscriptionLink = $objectManager->create(\Paytrail\PaymentService\Api\Data\SubscriptionLinkInterface::class);
$subscriptionLinkRepo = $objectManager->create(\Paytrail\PaymentService\Api\SubscriptionLinkRepositoryInterface::class);

$collection = $objectManager->create(\Magento\Sales\Model\ResourceModel\Order\Collection::class);
$collection->addFieldToFilter('customer_id', ['eq' => 1]); // customer_order fixture uses hardcoded customer id 1
$order = $objectManager->get(OrderInterfaceFactory::class)->create()->loadByIncrementId('55555555');
$order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
$order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
$order->save();

$profile->setName('Weekly');
$profile->setSchedule('"schedule": {"interval": 1, "unit": "W"}');
$profileRepo->save($profile);

$date = new \DateTime();
$date->modify('+3 day');

$payment->setNextOrderDate($date->format('Y-m-d H:i:s'));
$payment->setStatus(\Paytrail\PaymentService\Api\Data\SubscriptionInterface::STATUS_ACTIVE);
$payment->setRetryCount(5);
$payment->setRepeatCountLeft(5);
$payment->setRecurringProfileId($profile->getId());

$subscriptionRepository->save($payment);

$subscriptionLink->setOrderId($order->getId());
$subscriptionLink->setSubscriptionId($payment->getId());
$subscriptionLinkRepo->save($subscriptionLink);
