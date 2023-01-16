<?php
declare(strict_types=1);


namespace Paytrail\PaymentService\Model\Invoice;

use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Class InvoiceActivate
 */
class InvoiceActivation
{
    private const ACTIVE_INVOICE_CONFIG_PATH = 'payment/paytrail/activate_invoices_separately';
    public const SUB_METHODS_WITH_MANUAL_ACTIVATION_SUPPORT = [
        'collectorb2c',
        'collectorb2b',
    ];

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var string[]
     */
    private array $activationOverride;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param string[] $activationOverride
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        array $activationOverride = []
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->activationOverride = $activationOverride;
    }

    /**
     * Conditionally sets manual invoice activation flag to payment request based on admin configuration
     *
     * @param \Paytrail\SDK\Request\PaymentRequest $paytrailPayment
     * @param string $method
     * @param \Magento\Sales\Model\Order $order
     * @return \Paytrail\SDK\Request\PaymentRequest
     */
    public function setManualInvoiceActivationFlag(&$paytrailPayment, $method, $order)
    {
        if ($this->isManualInvoiceEnabled()
            && in_array($method, $this->getInvoiceMethods())
            && !$order->getIsVirtual()
        ) {
            $paytrailPayment->setManualInvoiceActivation(true);
        }

        return $paytrailPayment;
    }

    /**
     * Get admin config for invoice activation
     *
     * @return bool
     */
    private function isManualInvoiceEnabled(): bool
    {
        return (bool)$this->scopeConfig->getValue(
            self::ACTIVE_INVOICE_CONFIG_PATH,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Return invoice methods that support manual activation flag. Includes dependency injection extension point.
     *
     * @return string[]
     */
    private function getInvoiceMethods(): array
    {
        return array_merge(self::SUB_METHODS_WITH_MANUAL_ACTIVATION_SUPPORT, $this->activationOverride);
    }
}
