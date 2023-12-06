<?php

namespace Paytrail\PaymentService\Model\Order;

use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\DB\TransactionFactory;
use Psr\Log\LoggerInterface;

class OrderActivation
{
    /**
     * OrderActivation constructor.
     *
     * @param OrderRepositoryInterface $orderRepository
     * @param TransactionRepositoryInterface $transactionRepository
     * @param InvoiceService $invoiceService
     * @param TransactionFactory $transactionFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private TransactionRepositoryInterface $transactionRepository,
        private InvoiceService $invoiceService,
        private TransactionFactory $transactionFactory,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Activate order.
     *
     * @param string $orderId
     * @return void
     * @throws InputException
     */
    public function activateOrder($orderId)
    {
        $order = $this->orderRepository->get($orderId);

        /**
         * Loop through order items and set canceled items as ordered
         */
        foreach ($order->getItems() as $item) {
            $item->setQtyCanceled(0);
        }

        $this->orderRepository->save($order);
        $this->processInvoice($order);
    }

    /**
     * Is canceled value.
     *
     * @param string $orderId
     * @return bool
     * @throws InputException
     */
    public function isCanceled($orderId)
    {
        $order = $this->orderRepository->get($orderId);
        $i = 0;

        foreach ($order->getItems() as $item) {
            if ($item->getQtyCanceled() > 0) {
                $i++;
            }
        }

        $transactionId = $this->getCaptureTransaction($order);

        if ($i > 0 && $transactionId) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get capture transaction.
     *
     * @param OrderInterface $order
     * @return false
     * @throws InputException
     */
    protected function getCaptureTransaction($order)
    {
        $transactionId = false;
        $paymentId =  $order->getPayment()->getId();
        /* For backwards compatibility, e.g. Magento 2.2.9 requires 3 parameters. */
        $transaction = $this->transactionRepository->getByTransactionType('capture', $paymentId, $order->getId());
        if ($transaction) {
            $transactionId = $transaction->getTransactionId();
        }
        return $transactionId;
    }

    /**
     * Process invoice.
     *
     * @param OrderInterface $order
     * @return void
     * @throws InputException
     * @throws LocalizedException
     */
    protected function processInvoice($order)
    {
        $transactionId = $this->getCaptureTransaction($order);

        if ($order->canInvoice()) {
            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
            $invoice->setTransactionId($transactionId);
            $invoice->register();
            $this->saveInvoiceAndOrder($invoice, $order);
        }
    }

    /**
     * Save invoice and order.
     *
     * @param InvoiceInterface $invoice
     * @param OrderInterface $order
     * @return void
     * @throws LocalizedException
     */
    private function saveInvoiceAndOrder(InvoiceInterface $invoice, $order): void
    {
        try {
            /** @var \Magento\Framework\DB\Transaction $transactionSave */
            $transactionSave = $this->transactionFactory->create();
            $transactionSave->addObject(
                $invoice
            )->addObject(
                $order
            )->save();
        } catch (\Exception $e) {
            $message = __(
                'Paytrail unable to save re-active order from admin: %error',
                ['error' => $e->getMessage()]
            );
            $this->logger->critical($message);

            throw new LocalizedException($message);
        }
    }
}
