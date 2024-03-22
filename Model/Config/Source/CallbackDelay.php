<?php

namespace Paytrail\PaymentService\Model\Config\Source;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\OptionSourceInterface;

class CallbackDelay implements OptionSourceInterface
{
    private const CALLBACK_DELAY_PATH = 'payment/paytrail/callback_delay';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $optionArray = [];
        foreach (range(30, 300, 30) as $delayValue) {
            $optionArray[$delayValue] = $delayValue;
        }
        return $optionArray;
    }

    /**
     * CallbackDelay value getter
     *
     * @return int
     */
    public function getCallbackDelay(): int
    {
        return $this->scopeConfig->getValue(
            self::CALLBACK_DELAY_PATH
        );
    }
}
