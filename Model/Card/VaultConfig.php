<?php

namespace Paytrail\PaymentService\Model\Card;

use Magento\Framework\App\Config\ScopeConfigInterface;

class VaultConfig
{
    private const VAULT_FOR_PAYTRAIL_PATH = 'payment/paytrail_cc_vault/active';

    /**
     * VaultConfig constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Returns is CC_Vault for cards is enabled for Paytrail.
     *
     * @return bool
     */
    public function isVaultForPaytralEnabled(): bool
    {
        return (bool)$this->scopeConfig->getValue(self::VAULT_FOR_PAYTRAIL_PATH);
    }
}
