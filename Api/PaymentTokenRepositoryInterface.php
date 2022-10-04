<?php

namespace Paytrail\PaymentService\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface as BasePaymentTokenRepositoryInterface;

interface PaymentTokenRepositoryInterface extends BasePaymentTokenRepositoryInterface
{
    /**
     * @param PaymentTokenInterface $paymentToken
     * @param int $customerId
     * @return void
     *
     * @throws LocalizedException
     */
    public function validateTokensCustomer(PaymentTokenInterface $paymentToken, int $customerId): void;
}
