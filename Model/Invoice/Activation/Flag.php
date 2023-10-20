<?php
declare(strict_types=1);

namespace Paytrail\PaymentService\Model\Invoice\Activation;

use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\SDK\Request\AbstractPaymentRequest;

class Flag
{
    public const SUB_METHODS_WITH_MANUAL_ACTIVATION_SUPPORT = [
        'collectorb2c',
        'collectorb2b',
    ];

    /**
     * @var Config
     */
    private Config $config;

    /**
     * Array of sub method codes similar to "collectorb2c", codes added to array will get manual invoicing flag
     * during payment
     *
     * @var string[]
     */
    private array $activationOverride;

    /**
     * @param Config $config
     * @param array $activationOverride
     */
    public function __construct(
        Config $config,
        array $activationOverride = []
    ) {
        $this->config = $config;
        $this->activationOverride = $activationOverride;
    }

    /**
     * Conditionally sets manual invoice activation flag to payment request based on admin configuration.
     *
     * Virtual orders do not support manual invoice if shipment activation is enabled as virtual orders get no shipments
     *
     * @param AbstractPaymentRequest $paytrailPayment
     * @param string $method
     * @param \Magento\Sales\Model\Order $order
     * @return AbstractPaymentRequest
     */
    public function setManualInvoiceActivationFlag(
        AbstractPaymentRequest $paytrailPayment,
        string $method,
        $order
    ) : AbstractPaymentRequest {
        if ($this->isManualInvoiceEnabled()
            && in_array($method, $this->getInvoiceMethods())
            && (!$order->getIsVirtual() || !$this->config->isShipmentActivateInvoice())
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
        return $this->config->isManualInvoiceEnabled();
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
