<?php

namespace Paytrail\PaymentService\Plugin;

use Magento\Sales\Block\Adminhtml\Order\View\Tab\Info;

class PaymentMethodAdminOrderInfo
{
    /**
     * @param Info $subject
     * @return string
     */
    public function aroundGetPaymentHtml(Info $subject)
    {
        if ($subject->getOrder()->getPayment()->getMethod() === 'paytrail') {
            return $subject->getChildHtml('order_payment')
                . $subject->getOrder()->getPayment()->getAdditionalInformation()['method_title']
                . ' (' . $subject->getOrder()->getPayment()->getAdditionalInformation()['selected_payment_method'] . ')';
        } else {
            return $subject->getChildHtml('order_payment');
        }
    }
}
