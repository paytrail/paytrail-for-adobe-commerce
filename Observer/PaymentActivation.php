<?php
declare(strict_types=1);

namespace Paytrail\PaymentService\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Payment\Gateway\Command\CommandManagerPoolInterface;
use Magento\Sales\Model\ResourceModel\Order\Payment\Transaction\CollectionFactory;
use \Paytrail\PaymentService\Model\Invoice\InvoiceActivation as ActivationModel;

class PaymentActivation implements ObserverInterface
{
    /**
     * PaymentActivation constructor.
     *
     * @param CollectionFactory $collectionFactory
     * @param CommandManagerPoolInterface $commandManagerPool
     */
    public function __construct(
        private CollectionFactory $collectionFactory,
        private CommandManagerPoolInterface $commandManagerPool
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
        if ($shipment->getOrigData('entity_id')) {
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
                ActivationModel::SUB_METHODS_WITH_MANUAL_ACTIVATION_SUPPORT
            )) {
                $this->sendActivation($transaction->getTxnId());
            }
        }
    }

    /**
     * Send invoice activation.
     *
     * @param string $txnId
     * @return void
     */
    private function sendActivation($txnId)
    {
        // Activation returns a status "OK" if the payment was completed upon activation but the return has no signature
        // Without signature Hmac validation embedded in payment processing cannot be passed. This can be resolved with
        // Recurring payment HMAC updates.
        // TODO Use recurring payment HMAC processing here to mark order as paid if response status is "OK"

        $commandExecutor = $this->commandManagerPool->get('paytrail');
        $commandExecutor->executeByCode(
            'invoice_activation',
            null,
            [
                'transaction_id' => $txnId
            ]
        );
    }
}
