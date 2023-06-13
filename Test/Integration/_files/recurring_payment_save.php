<?php
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;
use Magento\TestFramework\Helper\Bootstrap;
use Paytrail\PaymentService\Api\RecurringProfileRepositoryInterface;

Resolver::getInstance()->requireDataFixture('Magento/Sales/_files/default_rollback.php');
Resolver::getInstance()->requireDataFixture('Paytrail_PaymentService::Test/Integration/_files/recurring_payment_rollback.php');
Resolver::getInstance()->requireDataFixture('Magento/Sales/_files/order.php');

$profileRepo = Bootstrap::getObjectManager()->create(RecurringProfileRepositoryInterface::class);
$profile = Bootstrap::getObjectManager()->create(\Paytrail\PaymentService\Api\Data\RecurringProfileInterface::class);

$profile->setName('TestProfile');
$profile->setDescription('Test Description');
$profile->setSchedule('0 0 * * 1');

$profileRepo->save($profile);

