<?php

namespace Paytrail\PaymentService\Api;

/**
 * @api
 */
interface PaymentSubscriptionManagementInterface
{
    /**
     * Show payment methods for customer subscriptions
     *
     * @return array|void
     */
    public function showSubscriptionPayment();
}
