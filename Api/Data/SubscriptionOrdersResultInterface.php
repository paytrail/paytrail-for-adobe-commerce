<?php

namespace Paytrail\PaymentService\Api\Data;

interface SubscriptionOrdersResultInterface
{
    /**
     * @return string
     */
    public function getSubscriptionId();

    /**
     * @param $subscriptionId
     * @return \Paytrail\PaymentService\Api\Data\SubscriptionOrdersResultInterface
     */
    public function setSubscriptionId($subscriptionId): self;
}
