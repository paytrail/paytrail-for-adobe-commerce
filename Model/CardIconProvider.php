<?php

namespace Paytrail\PaymentService\Model;

use Magento\Payment\Model\CcConfigProvider;

class CardIconProvider
{
    private CcConfigProvider $ccConfigProvider;
    
    public function __construct(
        CcConfigProvider $ccConfigProvider
    ) {
        $this->ccConfigProvider = $ccConfigProvider;
    }
    
    /**
     * @param $type
     * @return array|mixed
     */
    public function getIconForType($type)
    {
        if (isset($this->ccConfigProvider->getIcons()[$type])) {
            return $this->ccConfigProvider->getIcons()[$type];
        }

        return [
            'url' => '',
            'width' => 0,
            'height' => 0
        ];
    }
}
