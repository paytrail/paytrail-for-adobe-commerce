<?php
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Paytrail_PaymentService::Test/Integration/_files/recurring_payment_rollback.php');
Resolver::getInstance()->requireDataFixture('Paytrail_PaymentService::Test/Integration/_files/recurring_payment_save.php');

$subscriptionRepository = Bootstrap::getObjectManager()->create(\Paytrail\PaymentService\Api\SubscriptionRepositoryInterface::class);
$payment = Bootstrap::getObjectManager()->create(\Paytrail\PaymentService\Api\Data\SubscriptionInterface::class);

$collection = Bootstrap::getObjectManager()->create(\Magento\Sales\Model\ResourceModel\Order\Collection::class);
$orderId = $collection->getFirstItem()->getId();

$collection = Bootstrap::getObjectManager()->create(\Paytrail\PaymentService\Model\ResourceModel\Subscription\Profile\Collection::class);
$profileId = $collection->getFirstItem()->getId();

$payment->setOrderId($orderId);
$payment->setOriginalOrderId(null);
$payment->setRetryCount(5);
$payment->setRepeatCountLeft(5);
$payment->setStatus(\Paytrail\PaymentService\Api\Data\SubscriptionInterface::STATUS_ACTIVE);
$payment->setEndDate('2121-09-09 20:20:20');
$payment->setRecurringProfileId($profileId);

$subscriptionRepository->save($payment);
