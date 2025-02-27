<?php

namespace Goodahead\HyvaCheckout\Observer\Frontend;

use Magento\Framework\Event\Observer;

class HyvaCheckoutSessionReset extends \Hyva\Checkout\Observer\Frontend\HyvaCheckoutSessionReset
{
    public function execute(Observer $observer): void
    {}
}
