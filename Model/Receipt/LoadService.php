<?php

namespace Paytrail\PaymentService\Model\Receipt;

use Magento\Framework\Exception\InputException;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\OrderFactory;

class LoadService
{
    /**
     * LoadService constructor.
     *
     * @param TransactionRepositoryInterface $transactionRepository
     * @param OrderFactory $orderFactory
     * @param ProcessService $processService
     */
    public function __construct(
        private TransactionRepositoryInterface $transactionRepository,
        private OrderFactory $orderFactory,
        private ProcessService $processService
    ) {
    }

    /**
     * LoadTransaction function
     *
     * @param string $transactionId
     * @param $currentOrder
     * @param $orderId
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
            $this->processService->processError($e->getMessage());
        }

        return $transaction;
    }

    /**
     * LoadOrder function
     *
     * @param string $orderIncrementalId
     * @return mixed
     * @throws \Paytrail\PaymentService\Exceptions\CheckoutException
     */
    public function loadOrder($orderIncrementalId)
    {
        $order = $this->orderFactory->create()->loadByIncrementId($orderIncrementalId);
        if (!$order->getId()) {
            $this->processService->processError('Order not found');
        }
        return $order;
    }
}
