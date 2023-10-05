<?php
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;
use Magento\Framework\Registry;

Resolver::getInstance()->requireDataFixture('Paytrail_PaymentService::Test/Integration/_files/subscription_payment_rollback.php');
Resolver::getInstance()->requireDataFixture('Paytrail_PaymentService::Test/Integration/_files/customer_notify_rollback.php');
Resolver::getInstance()->requireDataFixture('Magento/Sales/_files/default_rollback.php');
$objectManager = Bootstrap::getObjectManager();

/** @var Registry $registry */
$registry = $objectManager->get(Registry::class);
$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

/** @var ResourceConnection $resource */
$resource = Bootstrap::getObjectManager()
    ->get(ResourceConnection::class);

$connection = $resource->getConnection('default');
$connection->delete($resource->getTableName('sequence_product'));
$connection->delete($resource->getTableName('paytrail_subscription_link'));

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);
