<?php

namespace Paytrail\PaymentService\Api;

use Magento\Framework\Exception\LocalizedException;

/**
 * @api
 */
interface CustomerTokensManagementInterface
{
    /**
     * Show payment methods for customer.
     *
     * @return \Paytrail\PaymentService\Api\Data\CustomerTokensResultInterface[]
     * @throws LocalizedException
     */
    public function showCustomerPaymentMethods(): array;
}
