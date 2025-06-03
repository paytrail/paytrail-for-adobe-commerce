<?php

namespace Paytrail\PaymentService\Api\Data;

interface RecurringProfileSearchResultInterface extends \Magento\Framework\Api\SearchResultsInterface
{
    /**
     * Get items.
     *
     * @return RecurringProfileInterface[] Array of collection items.
     */
    public function getItems();

    /**
     * Set items.
     *
     * @param RecurringProfileInterface[]|null $items
     *
     * @return $this
     */
    public function setItems(?array $items = null);
}
