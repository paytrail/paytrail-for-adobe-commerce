<?php

namespace Paytrail\PaymentService\Block\Adminhtml\RecurringProfile;

use Paytrail\PaymentService\Block\Adminhtml\Subscription\Edit\AbstractButton;

class NewButton extends AbstractButton
{
    public function getButtonData()
    {
        return [
            'label' => __('Back'),
            'on_click' => sprintf("location.href = '%s';", $this->getUrl('recurring_payments/profile/edit')),
            'class' => 'primary',
            'sort_order' => 10
        ];
    }
}
