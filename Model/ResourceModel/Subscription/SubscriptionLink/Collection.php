<?php

namespace Paytrail\PaymentService\Model\ResourceModel\Subscription\SubscriptionLink;

use Exception;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Paytrail\PaymentService\Api\Data\SubscriptionLinkSearchResultInterface;
use Paytrail\PaymentService\Model\Subscription\SubscriptionLink;
use Paytrail\PaymentService\Model\ResourceModel\Subscription\SubscriptionLink as SubscriptionLinkResource;

class Collection extends AbstractCollection implements SubscriptionLinkSearchResultInterface
{
    /** @var SearchCriteriaInterface */
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
     * @param DataObject[]|null $items
     *
     * @return Collection
     * @throws Exception
     */
    public function setItems(?array $items = null)
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
     * Get search criteria.
     *
     * @return SearchCriteriaInterface
     */
    public function getSearchCriteria()
    {
        return $this->searchCriteria;
    }

    /**
     * Set search criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return $this|Collection
     */
    public function setSearchCriteria(SearchCriteriaInterface $searchCriteria)
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
     *
     * @return Collection
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function setTotalCount($totalCount)
    {
        // total count is the collections size, do not modify it.
        return $this;
    }
}
