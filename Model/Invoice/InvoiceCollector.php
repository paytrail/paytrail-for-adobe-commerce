<?php

namespace Paytrail\PaymentService\Model\Invoice;

use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Class InvoiceWalleyCollector
 */
class InvoiceCollector
{
    private const ACTIVE_INVOICE_CONFIG_PATH = 'payment/paytrail/walley_collector_active_invoice';
    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return bool
     */
    public function isActiveInvoiceEnable(): bool
    {
        return $this->scopeConfig->getValue(self::ACTIVE_INVOICE_CONFIG_PATH);
    }
}
