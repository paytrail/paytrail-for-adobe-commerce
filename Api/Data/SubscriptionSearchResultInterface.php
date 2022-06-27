<?php
namespace Paytrail\PaymentService\Api\Data;

interface SubscriptionSearchResultInterface extends \Magento\Framework\Api\SearchResultsInterface
{
    /**
     * Get items.
     *
     * @return \Paytrail\PaymentService\Api\Data\SubscriptionInterface[] Array of collection items.
     */
    public function getItems();

    /**
     * Set items.
     *
     * @param \Paytrail\PaymentService\Api\Data\SubscriptionInterface[] $items
     * @return $this
     */
    public function setItems(array $items = null);
}
