<?php

use Magento\TestFramework\Helper\Bootstrap;
use Paytrail\PaymentService\Model\ResourceModel\Subscription\Collection;

/** @var \Magento\Framework\Registry $registry */
$registry = Bootstrap::getObjectManager()->get(\Magento\Framework\Registry::class);
$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

/** @var \Magento\Sales\Model\Order $order */
$paymentCollection = Bootstrap::getObjectManager()->create(Collection::class);
foreach ($paymentCollection as $payment) {
    $payment->delete();
}

$subscriptionLinkCollection = Bootstrap::getObjectManager()
    ->create(\Paytrail\PaymentService\Model\ResourceModel\Subscription\SubscriptionLink\Collection::class);
foreach ($subscriptionLinkCollection as $subscriptionLink) {
    $subscriptionLink->delete();
}

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);
