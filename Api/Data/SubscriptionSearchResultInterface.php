<?php
namespace Paytrail\PaymentService\Api\Data;

interface SubscriptionSearchResultInterface extends \Magento\Framework\Api\SearchResultsInterface
{
    /**
     * Get items.
     *
     * @return SubscriptionInterface[] Array of collection items.
     */
    public function getItems();

    /**
     * Set items.
     *
     * @param SubscriptionInterface[]|null $items
     *
     * @return $this
     */
    public function setItems(?array $items = null);
}
