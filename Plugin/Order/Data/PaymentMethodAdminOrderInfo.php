<?php

namespace Paytrail\PaymentService\Plugin\Order\Data;

use Magento\Sales\Block\Adminhtml\Order\View\Tab\Info;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Model\PaymentMethod\OrderPaymentMethodData;

class PaymentMethodAdminOrderInfo
{
    /**
     * Around plugin for getPaymentHtml method in Info class.
     *
     * @param Info $subject
     * @return string
     */
    public function aroundGetPaymentHtml(Info $subject)
    {
        if ($subject->getOrder()->getPayment()->getMethod() === Config::CODE) {
            return $subject->getChildHtml('order_payment')
                . $subject->getOrder()->getPayment()->getAdditionalInformation()[OrderPaymentMethodData::METHOD_TITLE_CODE]
                . ' ('
                . $subject->getOrder()->getPayment()->getAdditionalInformation()[OrderPaymentMethodData::SELECTED_PAYMENT_METHOD_CODE]
                . ')';
        } else {
            return $subject->getChildHtml('order_payment');
        }
    }
}
