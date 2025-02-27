<?php
/*
 *  Copyright © 2024 GoodAhead. All rights reserved.
 */
declare(strict_types=1);

namespace Goodahead\Paytrail\ViewModel;

use Goodahead\ABTesting\Model\ConfigProvider;
use Goodahead\ABTesting\Model\CookieProvider;
use Goodahead\Paytrail\Model\Config;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class PaytrailPaymentViewModel implements ArgumentInterface
{

    /**
     * @param Config $config
     * @param ConfigProvider $abTestingConfig
     * @param CookieProvider $cookieProvider
     */
    public function __construct(
        protected Config $config,
        protected ConfigProvider $abTestingConfig,
        protected CookieProvider $cookieProvider
    ) {}

    /**
     * @return bool
     */
    public function canDisplayAdditionalInformation(): bool
    {
        if (!$this->abTestingConfig->isCheckoutPaymentMethodsABEnabled()) {
            return $this->config->canShowAdditionalInformation();
        }

        if ($this->cookieProvider->get() == 1) {
            return $this->config->canShowAdditionalInformation();
        }

        return false;
    }
}
