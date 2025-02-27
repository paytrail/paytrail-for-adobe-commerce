<?php
/*
 *  Copyright © 2024 GoodAhead. All rights reserved.
 */

namespace Goodahead\HyvaCheckout\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{

    public const XML_PATH_CONFIG_SKIP_PAYMENT_REDIRECT_NOTIFICATION = 'hyva_themes_checkout/navigation/skip_redirect_notification';

    public const XML_PATH_CONFIG_PREPEND_COUNTRY_CODE_TO_TELEPHONE = 'hyva_themes_checkout/address_form/prepend_country_code_to_telephone';

    public const XML_PATH_CONFIG_COUNTRY_CODE_TO_PREPEND = 'hyva_themes_checkout/address_form/country_code';

    public const XML_PATH_CONFIG_PHONE_NUMBER_REGEX = 'hyva_themes_checkout/address_form/phone_number_regex';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        protected ScopeConfigInterface $scopeConfig
    ) {}

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::XML_PATH_CONFIG_SKIP_PAYMENT_REDIRECT_NOTIFICATION, ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return bool
     */
    public function isPrependCountryCodeEnabled(): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::XML_PATH_CONFIG_PREPEND_COUNTRY_CODE_TO_TELEPHONE,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string|null
     */
    public function getCountryCodeToTelephone(): ?string
    {
        $countryCode = (string) $this->scopeConfig->getValue(
            self::XML_PATH_CONFIG_COUNTRY_CODE_TO_PREPEND,
            ScopeInterface::SCOPE_STORE
        );

        if (empty(trim($countryCode))) {
            return null;
        }

        return trim($countryCode);
    }

    /**
     * @return string|null
     */
    public function getPhoneNumberRegex(): ?string
    {
        $phoneNumberRegex = (string) $this->scopeConfig->getValue(
            self::XML_PATH_CONFIG_PHONE_NUMBER_REGEX,
            ScopeInterface::SCOPE_STORE
        );

        if (empty(trim($phoneNumberRegex))) {
            return null;
        }

        return $phoneNumberRegex;
    }
}
