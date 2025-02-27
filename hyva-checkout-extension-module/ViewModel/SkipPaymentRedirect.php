<?php
/*
 *  Copyright © 2024 GoodAhead. All rights reserved.
 */

namespace Goodahead\HyvaCheckout\ViewModel;

use Goodahead\HyvaCheckout\Model\Config;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class SkipPaymentRedirect implements ArgumentInterface
{
    /**
     * @param Config $config
     */
    public function __construct(
        protected Config $config
    ) {}

    /**
     * @return bool
     */
    public function isSkipPaymentRedirectEnabled(): bool
    {
        return $this->config->isEnabled();
    }
}
