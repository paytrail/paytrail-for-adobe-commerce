<?php
declare(strict_types=1);

namespace Paytrail\PaymentService\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\ResourceModel\Order\Payment\Transaction\CollectionFactory;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Model\Invoice\Activation\Flag;
use Paytrail\PaymentService\Model\Invoice\Activation\ManualActivation;

class PaymentActivation implements ObserverInterface
{
    public const ACTIVATE_WITH_SHIPMENT_CONFIG = 'payment/paytrail/shipment_activates_invoice';

    /**
     * PaymentActivation constructor.
     *
     * @param CollectionFactory $collectionFactory
     * @param Config $config
     * @param ManualActivation $manualActivation
     */
    public function __construct(
        private CollectionFactory $collectionFactory,
        private Config $config,
        private ManualActivation $manualActivation
    ) {
    }

    /**
     * Execute.
     *
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

         /** @var \Magento\Sales\Model\ResourceModel\Order\Payment\Transaction\Collection $transactionCollection */
        $transactionCollection = $this->collectionFactory->create();
        $transactionCollection->addOrderIdFilter($shipment->getOrderId());

        /** @var \Magento\Sales\Api\Data\TransactionInterface $transaction */
        foreach ($transactionCollection->getItems() as $transaction) {
            $info = $transaction->getAdditionalInformation();

            if (isset($info['raw_details_info']['method']) && in_array(
                $info['raw_details_info']['method'],
                Flag::SUB_METHODS_WITH_MANUAL_ACTIVATION_SUPPORT
            )) {
                $this->manualActivation->activateInvoice((int)$shipment->getOrderId());
            }
        }
    }
}
