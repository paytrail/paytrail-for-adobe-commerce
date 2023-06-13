<?php

namespace Paytrail\PaymentService\Api;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\NoSuchEntityException;
use Paytrail\PaymentService\Api\Data\RecurringProfileInterface;
use Paytrail\PaymentService\Model\ResourceModel\Subscription\Collection as RecurringPaymentCollection;
use Paytrail\PaymentService\Model\ResourceModel\Subscription\Profile\Collection;
use PHPUnit\Framework\TestCase;
use Magento\TestFramework\Helper\Bootstrap;

class RecurringProfileRepositoryInterfaceTest extends TestCase
{
    /**
     * @var \Paytrail\PaymentService\Api\RecurringProfileRepositoryInterface
     */
    private $repository;

    public function deleteProvider()
    {
        return [
            'Deletion successful if no recurring payments exist' => [
                'withPayment' => false,
            ],
            'Deletion fails if a recurring payment exists' => [
                'withPayment' => true,
            ]
        ];
    }

    protected function setUp(): void
    {
        $this->repository = Bootstrap::getObjectManager()->create(RecurringProfileRepositoryInterface::class);
    }

    /**
     * @magentoDataFixture Paytrail_PaymentService::Test/Integration/_files/recurring_profile.php
     *
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function testSave()
    {
        $dataToSave = [
            'name' => 'testProfile',
            'schedule' => '0 0 * * 1',
            'description' => 'test description'
        ];

        $profile = Bootstrap::getObjectManager()->create(Data\RecurringProfileInterface::class);
        $profile->addData($dataToSave);
        $this->repository->save($profile);

        $savedProfile = $this->repository->get($profile->getId());
        foreach ($dataToSave as $key => $expected) {
            $this->assertEquals($expected, $savedProfile->getData($key));
        }
    }

    /**
     * @dataProvider deleteProvider
     * @magentoDataFixture Paytrail_PaymentService::Test/Integration/_files/subscription_payment.php
     *
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function testDelete($withPayment)
    {
        if ($withPayment) {
            $this->expectException(CouldNotDeleteException::class);
        } else {
            $payments = Bootstrap::getObjectManager()->create(RecurringPaymentCollection::class);

            foreach ($payments as $payment) {
                $payment->delete();
            }
        }

        $collection = Bootstrap::getObjectManager()->create(Collection::class);
        foreach ($collection as $recurringPayment) {
            $id = $recurringPayment->getId();
            $this->repository->delete($recurringPayment);

            try {
                $this->repository->get($id);
            } catch (NoSuchEntityException $e) {
                continue;
            }

            $this->fail('Recurring payment loaded after deletion');
        }
    }
    /**
     * @magentoDataFixture Paytrail_PaymentService::Test/Integration/_files/subscription_payment.php
     */
    public function testGetList()
    {
        $filterBuilder = Bootstrap::getObjectManager()->create(FilterBuilder::class);
        $filter1 = $filterBuilder->setField(RecurringProfileInterface::FIELD_NAME)
            ->setValue('testProfile')
            ->create();
        $filter2 = $filterBuilder->setField(RecurringProfileInterface::FIELD_SCHEDULE)
            ->setValue('0 0 * * 1')
            ->create();

        $searchCriteriaBuilder =  Bootstrap::getObjectManager()->create(SearchCriteriaBuilder::class);
        $searchCriteriaBuilder->addFilters([$filter1, $filter2]);
        $searchCriteria = $searchCriteriaBuilder->create();

        $searchResult = $this->repository->getList($searchCriteria);
        $this->assertCount(1, $searchResult->getItems());
        $this->assertEquals(
            '0 0 * * 1',
            $searchResult->getFirstItem()->getData(RecurringProfileInterface::FIELD_SCHEDULE)
        );
    }
}
