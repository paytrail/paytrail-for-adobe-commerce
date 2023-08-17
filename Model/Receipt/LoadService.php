<?php

namespace Paytrail\PaymentService\Model\Receipt;

use Magento\Framework\Exception\InputException;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\OrderFactory;
use Paytrail\PaymentService\Helper\Data as PaytrailHelper;

class LoadService
{
    /**
     * @param TransactionRepositoryInterface $transactionRepository
     * @param PaytrailHelper $paytrailHelper
     * @param OrderFactory $orderFactory
     */
    public function __construct(
        private TransactionRepositoryInterface $transactionRepository,
        private PaytrailHelper $paytrailHelper,
        private OrderFactory $orderFactory
    ) {
    }

    /**
     * LoadTransaction function
     * 
     * @param $transactionId
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
            $this->paytrailHelper->processError($e->getMessage());
        }

        return $transaction;
    }

    /**
     * LoadOrder function
     * 
     * @param $orderIncrementalId
     * @return mixed
     * @throws \Paytrail\PaymentService\Exceptions\CheckoutException
     */
    public function loadOrder($orderIncrementalId)
    {
        $order = $this->orderFactory->create()->loadByIncrementId($orderIncrementalId);
        if (!$order->getId()) {
            $this->paytrailHelper->processError('Order not found');
        }
        return $order;
    }
}
