<?php

namespace Paytrail\PaymentService\Ui\DataProvider;

use Magento\Backend\Model\Url;
use Magento\Framework\Api\Filter;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Paytrail\PaymentService\Model\ResourceModel\Subscription\Collection;
use Paytrail\PaymentService\Model\ResourceModel\Subscription\CollectionFactory;
use Paytrail\PaymentService\Model\Subscription;

class RecurringPaymentForm extends AbstractDataProvider
{
    /**
     * @var array
     */
    private $loadedData;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var Url
     */
    private $url;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param Url $url
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        Url $url,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collectionFactory = $collectionFactory;
        $this->url = $url;
    }

    /**
     * @return array
     */
    public function getData()
    {
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }

        $this->loadedData = [];
        foreach ($this->getCollection() as $subscription) {
            $this->prepareLinks($subscription);
            $this->loadedData[$subscription->getId()] = $subscription->getData();
        }

        return $this->loadedData;
    }

    /**
     * @param Filter $filter
     * @return mixed|void
     */
    public function addFilter(Filter $filter)
    {
        if ($filter->getField() == 'entity_id') {
            $filter->setField('main_table.entity_id');
        }

        parent::addFilter($filter); // TODO: Change the autogenerated stub
    }

    /**
     * @return Collection
     */
    public function getCollection()
    {
        if (!$this->collection) {
            $this->collection = $this->collectionFactory->create();
            $this->joinProfilesToCollection();
        }

        return $this->collection;
    }

    /**
     * @param Subscription $subscription
     */
    private function prepareLinks($subscription)
    {
        $subscription->setData(
            'profile_link',
            $this->createLinkData(
                'recurring_payments/profile/edit',
                ['id' => $subscription->getRecurringProfileId()],
                $subscription->getProfileName()
            )
        );
    }

    /**
     * @return void
     */
    private function joinProfilesToCollection()
    {
        $this->collection->join(
            ['rpp' => 'recurring_payment_profiles'],
            'main_table.recurring_profile_id = rpp.profile_id',
            ['profile_name' => 'name']
        );
    }

    /**
     * @param string $path
     * @param array $params
     * @param string $linkText
     * @return array
     */
    private function createLinkData(string $path, array $params, string $linkText)
    {
        return [
            'link_text' => $linkText,
            'link' => $this->url->getUrl(
                $path,
                $params
            )
        ];
    }
}
