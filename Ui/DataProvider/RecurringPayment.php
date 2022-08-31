<?php

namespace Paytrail\PaymentService\Ui\DataProvider;

use Magento\Backend\Model\UrlInterface;
use Paytrail\PaymentService\Model\ResourceModel\Subscription\CollectionFactory;

class RecurringPayment extends \Magento\Ui\DataProvider\AbstractDataProvider
{
    /** @var CollectionFactory */
    private $collectionFactory;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $meta,
            $data
        );

        $this->collectionFactory = $collectionFactory;
    }

    public function getData()
    {
        $collection = $this->getCollection();

        return $collection->toArray();
    }

    public function getCollection()
    {
        if (!$this->collection) {
            $this->collection = $this->collectionFactory->create();
            $this->collection->getSelect()
                ->join(
                    ['cu' => 'customer_entity'],
                    'main_table.customer_id = cu.entity_id',
                    ['cu.email']
                )->join(
                    ['sublink' => 'paytrail_subscription_link'],
                    'main_table.entity_id = sublink.subscription_id',
                    ['last_order_id' => 'MAX(sublink.order_id)']
                )->join(
                    ['profile' => 'recurring_payment_profiles'],
                    'main_table.recurring_profile_id = profile.profile_id',
                    ['profile_name' => 'profile.name']
                )->group('main_table.entity_id');
        }

        return $this->collection;
    }
}
