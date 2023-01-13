<?php
declare(strict_types=1);


namespace Paytrail\PaymentService\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Serialize\SerializerInterface;
use Paytrail\PaymentService\Helper\ApiData;
use \Paytrail\PaymentService\Model\Invoice\InvoiceActivation as ActivationModel;
use Paytrail\PaymentService\Model\ReceiptDataProvider;

class PaymentActivation implements \Magento\Framework\Event\ObserverInterface
{
    private \Magento\Sales\Model\ResourceModel\Order\Payment\Transaction\CollectionFactory $collectionFactory;
    private SerializerInterface $serializer;
    private ApiData $apiData;

    public function __construct(
        \Magento\Sales\Model\ResourceModel\Order\Payment\Transaction\CollectionFactory $collectionFactory,
        SerializerInterface $serializer,
        ApiData $apiData
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->serializer = $serializer;
        $this->apiData = $apiData;
    }

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

            /*
             * Read only previous api status to indicate if order needs to activate. Reading only config here can
             * Leave some orders stuck in pending state if admin disables delayed invoice activation when some orders
             * already are pending activation.
            */
            if (isset($info['raw_details_info']['method'])
                && in_array(
                    $info['raw_details_info']['method'],
                    ActivationModel::SUB_METHODS_WITH_MANUAL_ACTIVATION_SUPPORT
                ) && $info['raw_details_info']['api_status'] === ReceiptDataProvider::PAYTRAIL_API_PAYMENT_STATUS_PENDING
            ) {
                $this->sendActivation($transaction->getTxnId());
            }
        }
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderInterface $transaction
     * @return void
     */
    private function sendActivation($txnId)
    {
        // Activation returns a status "OK" if the payment was completed upon activation but the return has no signature
        // Without signature Hmac validation embedded in payment processing cannot be passed. This can be resolved with
        // Recurring payment HMAC updates.
        // TODO Use recurring payment HMAC processing here to mark order as paid if response status is "OK"
        $this->apiData->processApiRequest(
            'invoice_activation',
            null,
            null,
            $txnId
        );
    }
}
