<?php

namespace Paytrail\PaymentService\Ui\DataProvider;

use Magento\Framework\Serialize\SerializerInterface;

class RecurringProfileForm extends \Magento\Ui\DataProvider\AbstractDataProvider
{
    /** @var array */
    private $loadedData;

    /**
     * @var \Paytrail\PaymentService\Model\ResourceModel\Subscription\Profile\CollectionFactory
     */
    private $collectionFactory;
    /**
     * @var SerializerInterface
     */
    private $serializer;
    /**
     * @var array|bool|float|int|string|null
     */
    private $schedule;

    public function __construct(
                                                                                $name,
                                                                                $primaryFieldName,
                                                                                $requestFieldName,
        \Paytrail\PaymentService\Model\ResourceModel\Subscription\Profile\CollectionFactory $collectionFactory,
        SerializerInterface                                                     $serializer,
        array                                                                   $meta = [],
        array                                                                   $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collectionFactory = $collectionFactory;
        $this->serializer = $serializer;
    }

    public function getData()
    {
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }

        $this->loadedData = [];
        foreach ($this->getCollection() as $recurringProfile) {
            $recurringProfile->setData('interval_period', $this->parseSchedule('interval', $recurringProfile));
            $recurringProfile->setData('interval_unit', $this->parseSchedule('unit', $recurringProfile));
            $this->loadedData[$recurringProfile->getId()] = $recurringProfile->getData();
        }

        return $this->loadedData;
    }

    public function getCollection()
    {
        if (!$this->collection) {
            $this->collection = $this->collectionFactory->create();
        }

        return $this->collection;
    }

    /**
     * @param string $value
     * @param \Paytrail\PaymentService\Api\Data\RecurringProfileInterface $recurringProfile
     */
    private function parseSchedule(string $value, $recurringProfile)
    {
        if (!$this->schedule) {
            try {
                $this->schedule = $this->serializer->unserialize($recurringProfile->getSchedule());
            } catch (\InvalidArgumentException $e) {
                $this->schedule = [];
            }
        }

        return $this->schedule[$value] ?? null;
    }
}
