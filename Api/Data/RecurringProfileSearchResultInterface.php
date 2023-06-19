<?php

namespace Paytrail\PaymentService\Api\Data;

interface RecurringProfileSearchResultInterface extends \Magento\Framework\Api\SearchResultsInterface
{
    /**
     * Get items.
     *
     * @return \Paytrail\PaymentService\Api\Data\RecurringProfileInterface[] Array of collection items.
     */
    public function getItems();

    /**
     * Set items.
     *
     * @param \Paytrail\PaymentService\Api\Data\RecurringProfileInterface[] $items
     * @return $this
     */
    public function setItems(array $items = null);
}
