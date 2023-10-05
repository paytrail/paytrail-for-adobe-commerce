<?php

namespace Paytrail\PaymentService\Model\Invoice\Activation;

use Magento\Sales\Model\ResourceModel\Order\Payment\Transaction\CollectionFactory as TransactionCollectionFactory;
use Paytrail\PaymentService\Helper\ApiData;
use Paytrail\PaymentService\Model\ReceiptDataProvider;

class ManualActivation
{
    private TransactionCollectionFactory $collectionFactory;
    private ApiData $apiData;

    /**
     * @param TransactionCollectionFactory $collectionFactory
     * @param ApiData $apiData
     */
    public function __construct(
        TransactionCollectionFactory $collectionFactory,
        ApiData $apiData,
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->apiData = $apiData;
    }

    /**
     * @param int $orderId
     * @return void
     * @throws \Paytrail\SDK\Exception\ClientException
     * @throws \Paytrail\SDK\Exception\HmacException
     * @throws \Paytrail\SDK\Exception\RequestException
     * @throws \Paytrail\SDK\Exception\ValidationException
     */
    public function activateInvoice(int $orderId)
    {
        /** @var \Magento\Sales\Model\ResourceModel\Order\Payment\Transaction\Collection $transactions */
        $transactions = $this->collectionFactory->create();
        $transactions->addOrderIdFilter($orderId);

        /** @var \Magento\Sales\Api\Data\TransactionInterface $transaction */
        foreach ($transactions->getItems() as $transaction) {
            $info = $transaction->getAdditionalInformation();

            /*
             * Read only previous api status to indicate if order needs to activate. Reading only config here can
             * Leave some orders stuck in pending state if admin disables delayed invoice activation when some orders
             * are pending activation.
            */
            if (isset($info['raw_details_info']['method'])
                && in_array(
                    $info['raw_details_info']['method'],
                    Flag::SUB_METHODS_WITH_MANUAL_ACTIVATION_SUPPORT
                ) && $info['raw_details_info']['api_status'] === ReceiptDataProvider::PAYTRAIL_API_PAYMENT_STATUS_PENDING
            ) {
                $this->sendActivation($transaction->getTxnId());
            }
        }
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderInterface $transaction
     * @return void
     *
     * @throws \Paytrail\SDK\Exception\ClientException
     * @throws \Paytrail\SDK\Exception\HmacException
     * @throws \Paytrail\SDK\Exception\RequestException
     * @throws \Paytrail\SDK\Exception\ValidationException
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
