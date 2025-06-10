<?php

namespace Paytrail\PaymentService\Api\Data;

interface SubscriptionLinkSearchResultInterface extends \Magento\Framework\Api\SearchResultsInterface
{
    /**
     * Get items.
     *
     * @return SubscriptionLinkInterface[] Array of collection items.
     */
    public function getItems();

    /**
     * Set items.
     *
     * @param SubscriptionLinkInterface[]|null $items
     *
     * @return $this
     */
    public function setItems(?array $items = null);
}
