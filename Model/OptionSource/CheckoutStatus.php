<?php

namespace Paytrail\PaymentService\Model\OptionSource;

use Magento\Framework\Data\OptionSourceInterface;

class CheckoutStatus implements OptionSourceInterface
{
    /**
     * Provide options for filter.
     *
     * @return array[]
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 'ok',
                'label' => __('Ok')
            ],
            [
                'value' => 'fail',
                'label' => __('Fail')
            ]
        ];
    }
}
