<?php

namespace Paytrail\PaymentService\Model\Invoice\Activation;

use Magento\Payment\Gateway\Command\CommandManagerPoolInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\InvoiceOrder;
use Magento\Sales\Model\Order\Config;
use Magento\Sales\Model\Order\OrderStateResolverInterface;
use Magento\Sales\Model\ResourceModel\Order\Payment\Transaction\CollectionFactory as TransactionCollectionFactory;
use Paytrail\PaymentService\Gateway\Config\Config as GatewayConfig;

class ManualActivation
{
    /**
     * ManualActivation constructor.
     *
     * @param TransactionCollectionFactory $collectionFactory
     * @param InvoiceOrder $invoiceOrder
     * @param OrderStateResolverInterface $orderStateResolver
     * @param OrderRepositoryInterface $orderRepository
     * @param Config $config
     * @param GatewayConfig $gatewayConfig
     * @param CommandManagerPoolInterface $commandManagerPool
     */
    public function __construct(
        private TransactionCollectionFactory      $collectionFactory,
        private InvoiceOrder                      $invoiceOrder,
        private OrderStateResolverInterface       $orderStateResolver,
        private OrderRepositoryInterface          $orderRepository,
        private Config $config,
        private GatewayConfig $gatewayConfig,
        private CommandManagerPoolInterface $commandManagerPool
    ) {
    }

    /**
     * Activate invoice.
     *
     * @param int $orderId
     * @return void
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
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
                ) && $info['raw_details_info']['api_status'] === GatewayConfig::PAYTRAIL_API_PAYMENT_STATUS_PENDING
            ) {
                $this->sendActivation($transaction->getTxnId(), $orderId);
            }
        }
    }

    /**
     * Send invoice activation to Paytrail while submit shipment.
     *
     * @param string $txnId
     * @param int $orderId
     * @return void
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function sendActivation($txnId, $orderId)
    {
        // Activation returns a status "OK" if the payment was completed upon activation but the return has no signature
        // Without signature Hmac validation embedded in payment processing cannot be passed. This can be resolved with
        // Recurring payment HMAC updates.
        // TODO Use recurring payment HMAC processing here to mark order as paid if response status is "OK"
        $order = $this->orderRepository->get($orderId);
        if (!$order->hasInvoices()) {
            $commandExecutor = $this->commandManagerPool->get('paytrail');
            $response = $commandExecutor->executeByCode(
                'invoice_activation',
                null,
                [
                    'transaction_id' => $txnId
                ]
            );

            if ($response['data']->getStatus() === 'ok') {
                $invoiceResult = $this->invoiceOrder->execute($orderId, true);
                if ($invoiceResult) {
                    $state = $this->orderStateResolver->getStateForOrder(
                        $order,
                        [OrderStateResolverInterface::IN_PROGRESS]
                    );
                    $order->setState($state);
                    if ($state == 'complete') {
                        $order->setStatus($this->config->getStateDefaultStatus($order->getState()));

                    } else {
                        $order->setStatus($this->gatewayConfig->getDefaultOrderStatus());
                    }
                    $this->orderRepository->save($order);
                }
            }
        }
    }
}
