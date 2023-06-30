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
     * @var PreventAdminActions
     */
    private PreventAdminActions $preventAdminActions;

    /**
     * @param PreventAdminActions $preventAdminActions
     */
    public function __construct(
        PreventAdminActions $preventAdminActions
    ) {
        $this->preventAdminActions = $preventAdminActions;
    }

    /**
     * @param PaymentInformationManagement $subject
     * @param $cartId
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @return array
     * @throws ValidationException
     */
    public function beforeSavePaymentInformation(PaymentInformationManagement $subject, $cartId, PaymentInterface $paymentMethod, \Magento\Quote\Api\Data\AddressInterface $billingAddress = null): array
    {
        if ($this->preventAdminActions->isAdminAsCustomer()) {
            throw new ValidationException(__('Admin user is not authorized for this operation'));
        }

        return [$cartId, $paymentMethod, $billingAddress];
    }
}
