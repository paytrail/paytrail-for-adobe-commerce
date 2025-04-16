<?php

namespace Paytrail\PaymentService\Model\ResourceModel\Subscription\Profile;

use Exception;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Paytrail\PaymentService\Api\Data\RecurringProfileSearchResultInterface;
use Paytrail\PaymentService\Model\Subscription\Profile;
use Paytrail\PaymentService\Model\ResourceModel\Subscription\Profile as ProfileResource;

class Collection extends AbstractCollection implements RecurringProfileSearchResultInterface
{
    private SearchCriteriaInterface $searchCriteria;

    protected function _construct()
    {
        $this->_init(
            Profile::class,
            ProfileResource::class
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

    public function getSearchCriteria()
    {
        return $this->searchCriteria;
    }

    /**
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
