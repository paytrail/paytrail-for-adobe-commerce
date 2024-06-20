<?php

namespace Paytrail\PaymentService\Plugin\Magento\Checkout\Model;

use Magento\Checkout\Model\PaymentInformationManagement;
use Magento\Framework\Validation\ValidationException;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Paytrail\PaymentService\Model\Validation\PreventAdminActions;

class PaymentInformationManagementPlugin
{
    /**
     * Paytrail payment method code
     */
    public const PAYTRAIL = 'paytrail';

    /**
     * @var PreventAdminActions
     */
    private PreventAdminActions $preventAdminActions;

    /**
     * Constructor
     *
     * @param PreventAdminActions $preventAdminActions
     */
    public function __construct(
        PreventAdminActions $preventAdminActions
    ) {
        $this->preventAdminActions = $preventAdminActions;
    }

    /**
     * Validate if admin user is not authorized for this operation
     *
     * @param PaymentInformationManagement $subject
     * @param int $cartId
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     *
     * @return array
     * @throws ValidationException
     */
    public function beforeSavePaymentInformation(
        PaymentInformationManagement $subject,
                                     $cartId,
        PaymentInterface             $paymentMethod,
        AddressInterface             $billingAddress = null
    ): array {
        if ($this->preventAdminActions->isAdminAsCustomer()
            && str_contains(
                $paymentMethod->getMethod(),
                self::PAYTRAIL
            )
        ) {
            throw new ValidationException(__('Admin user is not authorized for this operation'));
        }

        return [$cartId, $paymentMethod, $billingAddress];
    }
}
