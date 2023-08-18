<?php

namespace Paytrail\PaymentService\Model\Receipt;

use Magento\Framework\Exception\InputException;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Logger\PaytrailLogger;

class LoadService
{
    /**
     * LoadService constructor.
     *
     * @param TransactionRepositoryInterface $transactionRepository
     * @param OrderFactory $orderFactory
     * @param PaytrailLogger $paytrailLogger
     */
    public function __construct(
        private TransactionRepositoryInterface $transactionRepository,
        private OrderFactory $orderFactory,
        private PaytrailLogger $paytrailLogger
    ) {
    }

    /**
     * LoadTransaction function
     *
     * @param string $transactionId
     * @param Order $currentOrder
     * @param string $orderId
     * @return bool|mixed
     * @throws \Paytrail\PaymentService\Exceptions\CheckoutException
     */
    public function loadTransaction($transactionId, $currentOrder, $orderId)
    {
        /** @var bool|mixed $transaction */
        try {
            $transaction = $this->transactionRepository->getByTransactionId(
                $transactionId,
                $currentOrder->getPayment()->getId(),
                $orderId
            );
        } catch (InputException $e) {
            $this->paytrailLogger->logData(\Monolog\Logger::ERROR, $e->getMessage());
            throw new CheckoutException(__($e->getMessage()));
        }

        return $transaction;
    }

    /**
     * LoadOrder function
     *
     * @param string $orderIncrementalId
     * @return Order
     * @throws CheckoutException
     */
    public function loadOrder($orderIncrementalId)
    {
        $order = $this->orderFactory->create()->loadByIncrementId($orderIncrementalId);
        if (!$order->getId()) {
            $this->paytrailLogger->logData(\Monolog\Logger::ERROR, 'Order not found');
            throw new CheckoutException(__('Order not found'));
        }
        return $order;
    }
}
