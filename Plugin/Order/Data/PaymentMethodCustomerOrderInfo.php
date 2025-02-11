<?php

namespace Paytrail\PaymentService\Plugin\Order\Data;

use Magento\Sales\Block\Order\Info;
use Paytrail\PaymentService\Gateway\Config\Config;

class PaymentMethodCustomerOrderInfo
{
    /**
     * @return string
     */
    public function aroundGetPaymentInfoHtml(Info $subject)
    {
        if ($subject->getOrder()->getPayment()->getMethod() === Config::CODE) {
            return $subject->getOrder()->getPayment()->getAdditionalInformation()['method_title']
                . ' (' . $subject->getOrder()->getPayment()->getAdditionalInformation()['selected_payment_method']
                . ')';
        } else {
            return $subject->getChildHtml('payment_info');
        }
    }
}
