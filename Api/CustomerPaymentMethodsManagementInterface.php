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
     * @return \Paytrail\PaymentService\Api\Data\CustomerPaymentMethodsResultInterface[]
     */
    public function showCustomerPaymentMethods(): array;
}
