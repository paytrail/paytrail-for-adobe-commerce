<?php

namespace Paytrail\PaymentService\Api;

/**
 * @api
 */
interface CustomerPaymentMethodsManagementInterface
{
    /**
     * Show payment methods for customer
     *
     * @return array|void
     */
    public function showCustomerPaymentMethods();
}
