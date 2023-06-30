<?php

namespace Paytrail\PaymentService\Model\ResourceModel\Subscription\SubscriptionLink;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Paytrail\PaymentService\Api\Data\SubscriptionLinkSearchResultInterface;
use Paytrail\PaymentService\Model\Subscription\SubscriptionLink;
use Paytrail\PaymentService\Model\ResourceModel\Subscription\SubscriptionLink as SubscriptionLinkResource;

class Collection extends AbstractCollection implements SubscriptionLinkSearchResultInterface
{
    /** @var \Magento\Framework\Api\SearchCriteriaInterface */
    private $searchCriteria;

    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            SubscriptionLink::class,
            SubscriptionLinkResource::class
        );
    }

    /**
     * Set items list.
     *
     * @param \Magento\Framework\DataObject[] $items
     * @return \Paytrail\PaymentService\Model\ResourceModel\Subscription\SubscriptionLink\Collection
     * @throws \Exception
     */
    public function setItems(array $items = null)
    {
        if (!$items) {
            return $this;
        }
        foreach ($items as $item) {
            $this->addItem($item);
        }
        return $this;
    }

    /**
     * @return \Magento\Framework\Api\SearchCriteriaInterface
     */
    public function getSearchCriteria()
    {
        return $this->searchCriteria;
    }

    /**
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return $this|Collection
     */
    public function setSearchCriteria(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria)
    {
        $this->searchCriteria = $searchCriteria;

        return $this;
    }

    /**
     * Get total count.
     *
     * @return int
     */
    public function getTotalCount()
    {
        return $this->getSize();
    }

    /**
     * Set total count.
     *
     * @param int $totalCount
     * @return \Paytrail\PaymentService\Model\ResourceModel\Subscription\SubscriptionLink\Collection
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function setTotalCount($totalCount)
    {
        // total count is the collections size, do not modify it.
        return $this;
    }
}
