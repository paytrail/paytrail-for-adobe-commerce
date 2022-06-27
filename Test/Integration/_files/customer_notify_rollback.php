<?php
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Paytrail_PaymentService::Test/Integration/_files/recurring_payment_rollback.php');
Resolver::getInstance()->requireDataFixture('Magento/Sales/_files/customer_order_with_simple_product_rollback.php');

$subscriptionLink = Bootstrap::getObjectManager()->create(\Paytrail\PaymentService\Api\Data\SubscriptionLinkInterface::class);
$subscriptionLinkRepo = Bootstrap::getObjectManager()->create(\Paytrail\PaymentService\Api\SubscriptionLinkRepositoryInterface::class);

$subscriptionLinkRepo->delete($subscriptionLink);

/** @var ResourceConnection $resource */
$resource = Bootstrap::getObjectManager()
    ->get(ResourceConnection::class);

$connection = $resource->getConnection('default');
$connection->delete($resource->getTableName('sequence_product'));
$connection->delete($resource->getTableName('paytrail_subscription_link'));
