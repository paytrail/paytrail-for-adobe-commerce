<?php

namespace Paytrail\PaymentService\Plugin\Order\Data;

use Magento\Sales\Block\Adminhtml\Order\View\Tab\Info;
use Paytrail\PaymentService\Gateway\Config\Config;

class PaymentMethodAdminOrderInfo
{
    /**
     * @param Info $subject
     * @return string
     */
    public function aroundGetPaymentHtml(Info $subject)
    {
        if ($subject->getOrder()->getPayment()->getMethod() === Config::CODE) {
            return $subject->getChildHtml('order_payment')
                . $subject->getOrder()->getPayment()->getAdditionalInformation()['method_title']
                . ' (' . $subject->getOrder()->getPayment()->getAdditionalInformation()['selected_payment_method']
                . ')';
        } else {
            return $subject->getChildHtml('order_payment');
        }
    }
}
