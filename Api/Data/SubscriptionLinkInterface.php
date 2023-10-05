<?php

namespace Paytrail\PaymentService\Api\Data;

interface SubscriptionLinkInterface
{
    const FIELD_LINK_ID = 'link_id';
    const FIELD_ORDER_ID = 'order_id';
    const FIELD_SUBSCRIPTION_ID = 'subscription_id';

    /**
     * @return int
     */
    public function getId();

    /**
     * @return string
     */
    public function getOrderId();

    /**
     * @return string
     */
    public function getSubscriptionId();

    /**
     * @param $linkId
     * @return $this
     */
    public function setId($linkId): self;

    /**
     * @param $orderId
     * @return $this
     */
    public function setOrderId($orderId): self;

    /**
     * @param $subscriptionId
     * @return $this
     */
    public function setSubscriptionId($subscriptionId): self;
}
