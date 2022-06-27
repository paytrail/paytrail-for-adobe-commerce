<?php

namespace Paytrail\PaymentService\Ui\DataProvider;

class RecurringPayment extends \Magento\Ui\DataProvider\AbstractDataProvider
{
    /** @var \Paytrail\PaymentService\Model\ResourceModel\Subscription\CollectionFactory */
    private $collectionFactory;

    public function __construct(
                                                                        $name,
                                                                        $primaryFieldName,
                                                                        $requestFieldName,
        \Paytrail\PaymentService\Model\ResourceModel\Subscription\CollectionFactory $collectionFactory,
        array                                                           $meta = [],
        array                                                           $data = []
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
        }

        return $this->collection;
    }
}
