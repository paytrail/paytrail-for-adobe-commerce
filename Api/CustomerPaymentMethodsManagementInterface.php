<?php

namespace Paytrail\PaymentService\Api;

use Magento\Framework\Exception\LocalizedException;

/**
 * @api
 */
interface CustomerPaymentMethodsManagementInterface
{
    /**
     * Show payment methods for customer
     *
     * @return \Paytrail\PaymentService\Api\Data\CustomerPaymentMethodsResultInterface[]
     * @throws LocalizedException
     */
    public function showCustomerPaymentMethods(): array;
}
