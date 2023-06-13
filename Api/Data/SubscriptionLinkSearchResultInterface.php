<?php

namespace Paytrail\PaymentService\Api\Data;

interface SubscriptionLinkSearchResultInterface extends \Magento\Framework\Api\SearchResultsInterface
{
    /**
     * Get items.
     *
     * @return \Paytrail\PaymentService\Api\Data\SubscriptionLinkInterface[] Array of collection items.
     */
    public function getItems();

    /**
     * Set items.
     *
     * @param \Paytrail\PaymentService\Api\Data\SubscriptionLinkInterface[] $items
     * @return $this
     */
    public function setItems(array $items = null);
}
