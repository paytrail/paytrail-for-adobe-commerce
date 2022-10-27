<?php

namespace Paytrail\PaymentService\Observer\Checkout;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Validation\ValidationException;
use Paytrail\PaymentService\Model\Validation\PreventAdminActions;

class ValidateCustomer implements ObserverInterface
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

    public function execute(Observer $observer)
    {
        if ($this->preventAdminActions->isAdminAsCustomer()) {
            throw new ValidationException(__('Admin user is not authorized for this operation'));
        }
    }
}
