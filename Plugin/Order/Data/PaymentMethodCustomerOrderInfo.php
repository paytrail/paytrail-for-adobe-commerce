<?php

namespace Paytrail\PaymentService\Plugin\Order\Data;

use Magento\Sales\Block\Order\Info;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Model\PaymentMethod\OrderPaymentMethodData;

class PaymentMethodCustomerOrderInfo
{
    /**
     * Around plugin for getPaymentInfoHtml method in Info class.
     *
     * @return string
     */
    public function aroundGetPaymentInfoHtml(Info $subject)
    {
        if ($subject->getOrder()->getPayment()->getMethod() === Config::CODE) {
            return $subject->getOrder()->getPayment()->getAdditionalInformation()[OrderPaymentMethodData::METHOD_TITLE_CODE]
                . ' (' . $subject->getOrder()->getPayment()->getAdditionalInformation()[OrderPaymentMethodData::SELECTED_PAYMENT_METHOD_CODE]
                . ')';
        } else {
            return $subject->getChildHtml('payment_info');
        }
    }
}
