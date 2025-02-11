<?php

namespace Paytrail\PaymentService\Plugin;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Block\Adminhtml\Order\View\Tab\Info;
use Magento\Sales\Model\ResourceModel\Order\Grid\Collection;

class PaymentMethodOrderInfo
{
    /**
     * @param Info $subject
     * @return string
     */
    public function aroundGetPaymentHtml(Info $subject)
    {
        $subject->setTitle('paytrail');

        return $subject->getChildHtml('order_payment')
            . $subject->getOrder()->getPayment()->getAdditionalInformation()['method_title'];
    }
}
