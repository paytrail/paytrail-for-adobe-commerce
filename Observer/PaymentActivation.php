<?php
declare(strict_types=1);

namespace Paytrail\PaymentService\Observer;

use Magento\Framework\Event\Observer;
use Paytrail\PaymentService\Model\Invoice\Activation\ManualActivation;

class PaymentActivation implements \Magento\Framework\Event\ObserverInterface
{
    public const ACTIVATE_WITH_SHIPMENT_CONFIG = 'payment/paytrail/shipment_activates_invoice';

    /**
     * @var \Paytrail\PaymentService\Gateway\Config\Config
     */
    private \Paytrail\PaymentService\Gateway\Config\Config $config;

    /**
     * @var ManualActivation
     */
    private ManualActivation $manualActivation;

    /**
     * @param \Paytrail\PaymentService\Gateway\Config\Config $config
     */
    public function __construct(
        \Paytrail\PaymentService\Gateway\Config\Config $config,
        ManualActivation $manualActivation
    ) {
        $this->config = $config;
        $this->manualActivation = $manualActivation;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order\Shipment $shipment */
        $shipment = $observer->getEvent()->getShipment();
        if ($shipment->getOrigData('entity_id') || !$this->config->isShipmentActivateInvoice()) {
            return; // Observer only processes shipments the first time they're made.
        }

        $this->manualActivation->activateInvoice((int)$shipment->getOrderId());
    }
}
