<?php
/*
 *  Copyright © 2024 GoodAhead. All rights reserved.
 */
declare(strict_types=1);

namespace Goodahead\Paytrail\Model;

use Goodahead\Optimizations\Service\CurrentStoreInformationService as StoreInformation;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Config
{
    public const XML_PATH_PAYMENT_PAYTRAIL_SHOW_ADDITIONAL_INFORMATION = 'payment/paytrail/show_additional_information';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param StoreInformation $storeInformation
     */
    public function __construct(
        protected ScopeConfigInterface $scopeConfig,
        protected StoreManagerInterface $storeManager,
        protected StoreInformation $storeInformation
    ) {}

    public function canShowAdditionalInformation(): bool
    {
        $websiteId = $this->storeInformation->execute(StoreInformation::KEY_WEBSITE_ID);

        return (bool) $this->scopeConfig->getValue(
            self::XML_PATH_PAYMENT_PAYTRAIL_SHOW_ADDITIONAL_INFORMATION,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        );
    }
}
