<?php

namespace Paytrail\PaymentService\Model\Invoice;

use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Class InvoiceActivate
 */
class InvoiceActivate
{
    private const ACTIVE_INVOICE_CONFIG_PATH = 'payment/paytrail/walley_collector_active_invoice';
    public const COLLECTOR_PAYMENT_METHOD_CODE = 'collectorb2c';

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
     * @param $paytrailPayment
     * @param array $requestParams
     * @return void
     */
    public function setManualInvoiceActivationFlag($paytrailPayment, $requestParams)
    {
        if (!$this->isActiveInvoiceEnable()
            && $requestParams['preselected_payment_method_id'] === self::COLLECTOR_PAYMENT_METHOD_CODE) {
            $paytrailPayment->setManualInvoiceActivation(true);
        }

        return $paytrailPayment;
    }

    /**
     * @return bool
     */
    private function isActiveInvoiceEnable(): bool
    {
        return $this->scopeConfig->getValue(self::ACTIVE_INVOICE_CONFIG_PATH);
    }
}
