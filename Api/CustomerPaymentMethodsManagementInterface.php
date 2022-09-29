<?php

namespace Paytrail\PaymentService\Api;

/**
 * @api
 */
interface CustomerPaymentMethodsManagementInterface
{
    /**
     * Show payment methods for customer subscriptions
     *
     * @return array|void
     */
    public function showCustomerPaymentMethods();
}
