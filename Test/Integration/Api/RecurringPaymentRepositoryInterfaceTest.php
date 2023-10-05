<?php

namespace Paytrail\PaymentService\Api;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Paytrail\PaymentService\Api\Data\SubscriptionInterface;
use Paytrail\PaymentService\Model\ResourceModel\Subscription\Profile\Collection;
use PHPUnit\Framework\TestCase;
use Magento\TestFramework\Helper\Bootstrap;

class RecurringPaymentRepositoryInterfaceTest extends TestCase
{
    /**
     * @var \Paytrail\PaymentService\Api\SubscriptionRepositoryInterface
     */
    private $repository;

    public function saveProvider()
    {
        return [
            'Save fails without recurring profile' => [
                'error' => CouldNotSaveException::class,
                'paymentData' => [
                    'original_order_id'     => 1,
                    'order_id'              => 1,
                    'status'                => SubscriptionInterface::STATUS_ACTIVE,
                    'recurring_profile_id'  => null,
                    'end_date'              => '2121-09-17 09:58:43'
                ]
            ],
            'Save fails on incorrect recurring profile' => [
                'error' => CouldNotSaveException::class,
                'paymentData' => [
                    'original_order_id'     => 1,
                    'order_id'              => 1,
                    'status'                => SubscriptionInterface::STATUS_ACTIVE,
                    'recurring_profile_id'  => 99999,
                    'end_date'              => '2121-09-17 09:58:43'
                ]
            ],
            'Save is successful with correct data' => [
                'error' => false,
                'paymentData' => [
                    'original_order_id'     => null,
                    'order_id'              => 'fetchValid',
                    'status'                => SubscriptionInterface::STATUS_ACTIVE,
                    'recurring_profile_id'  => 'fetchValid',
                    'end_date'              => '2121-09-17 09:58:43'
                ]
            ]
        ];
    }

    protected function setUp(): void
    {
        $this->repository = Bootstrap::getObjectManager()->create(SubscriptionRepositoryInterface::class);
    }

    /**
     * @dataProvider saveProvider
     * @magentoDataFixture Paytrail_PaymentService::Test/Integration/_files/recurring_payment_save.php
     *
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function testSave($error, $paymentData)
    {
        if ($error) {
            $this->expectException($error);
        }

        $payment = Bootstrap::getObjectManager()->create(SubscriptionInterface::class);
        foreach ($paymentData as $key => $data) {
            if ($data === 'fetchValid') {
                $collection = Bootstrap::getObjectManager()->create($this->getCollectionClassName($key));
                $data = $collection->getFirstItem()->getId();
            }
            $payment->setData($key, $data);
        }

        $this->repository->save($payment);
        $savedPayment = $this->repository->get($payment->getId());
        foreach ($paymentData as $key => $expected) {
            if ($expected === 'fetchValid') {
                $this->assertGreaterThanOrEqual(1, $savedPayment->getData($key));
                continue;
            }

            $this->assertEquals($expected, $savedPayment->getData($key));
        }
    }

    /**
     * @magentoDataFixture Paytrail_PaymentService::Test/Integration/_files/subscription_payment.php
     *
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function testDelete()
    {
        $collection = Bootstrap::getObjectManager()->create(\Paytrail\PaymentService\Model\ResourceModel\Subscription\Collection::class);

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
        $filter1 = $filterBuilder->setField(SubscriptionInterface::FIELD_END_DATE)
            ->setValue('2121-09-09 20:20:20')
            ->create();
        $filter2 = $filterBuilder->setField(SubscriptionInterface::FIELD_REPEAT_COUNT_LEFT)
            ->setValue(5)
            ->create();

        $searchCriteriaBuilder =  Bootstrap::getObjectManager()->create(SearchCriteriaBuilder::class);
        $searchCriteriaBuilder->addFilters([$filter1, $filter2]);
        $searchCriteria = $searchCriteriaBuilder->create();

        $searchResult = $this->repository->getList($searchCriteria);
        $this->assertCount(1, $searchResult->getItems());
        $this->assertEquals(
            '2121-09-09 20:20:20',
            $searchResult->getFirstItem()->getData(SubscriptionInterface::FIELD_END_DATE)
        );
    }

    private function getCollectionClassName($key)
    {
        if ($key === 'recurring_profile_id') {
            return Collection::class;
        } elseif ($key === 'order_id') {
            return \Magento\Sales\Model\ResourceModel\Order\Collection::class;
        }

        throw new LocalizedException(__('Could not load collection with key: %key', ['key' => $key]));
    }
}
