<?php

namespace Paytrail\PaymentService\Model\OptionSource;

class IntervalUnits implements \Magento\Framework\Data\OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            [
                'value' => 'D',
                'label' => __('Days')
            ],
            [
                'value' => 'W',
                'label' => __('Weeks')
            ],
            [
                'value' => 'M',
                'label' => __('Months')
            ],
            [
                'value' => 'Y',
                'label' => __('Years')
            ],
        ];
    }
}
