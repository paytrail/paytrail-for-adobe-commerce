<?php

namespace Paytrail\PaymentService\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Paytrail\PaymentService\Api\PaymentTokenRepositoryInterface;

class PaymentTokenRepository extends \Magento\Vault\Model\PaymentTokenRepository implements PaymentTokenRepositoryInterface
{
    /**
     * @inheritdoc
     */
    public function validateTokensCustomer(PaymentTokenInterface $paymentToken, int $customerId): void
    {
        if ((int)$paymentToken->getCustomerId() !== $customerId) {
            throw new LocalizedException(__("The payment token doesn't belong to the customer"));
        }
    }
}
