<?php

namespace Paytrail\PaymentService\Model;

use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Helper\Data as PaytrailHelper;
use Paytrail\PaymentService\Model\Receipt\LoadService;
use Paytrail\PaymentService\Model\Receipt\OrderLockService;
use Paytrail\PaymentService\Model\Receipt\PaymentTransaction;
use Paytrail\PaymentService\Model\Receipt\ProcessService;

class ReceiptDataProvider
{
    /**
     * @param Session $session
     * @param PaytrailHelper $paytrailHelper
     * @param Config $gatewayConfig
     * @param OrderLockService $orderLockService
     * @param ProcessService $processService
     * @param LoadService $loadService
     * @param PaymentTransaction $paymentTransaction
     */
    public function __construct(
        private Session $session,
        private PaytrailHelper $paytrailHelper,
        private Config $gatewayConfig,
        private OrderLockService $orderLockService,
        private ProcessService $processService,
        private LoadService $loadService,
        private PaymentTransaction $paymentTransaction
    ) {
    }

    /**
     * Execute function
     *
     * @param array $params
     * @throws CheckoutException
     * @throws LocalizedException
     */
    public function execute(array $params)
    {
        if ($this->gatewayConfig->getGenerateReferenceForOrder()) {
            $this->orderIncrementalId = $this->paytrailHelper->getIdFromOrderReferenceNumber(
                $params["checkout-reference"]
            );
        } else {
            $this->orderIncrementalId
                = $params["checkout-reference"];
        }
        $this->transactionId        =   $params["checkout-transaction-id"];
        $this->paramsStamp          =   $params['checkout-stamp'];
        $this->paramsMethod         =   $params['checkout-provider'];

        $this->session->unsCheckoutRedirectUrl();

        $this->currentOrder = $this->loadService->loadOrder($this->orderIncrementalId);
        $this->orderId = $this->currentOrder->getId();

        /** @var int $count */
        $count = 0;

        while ($this->orderLockService->isOrderLocked($this->orderId) && $count < 3) {
//            sleep(1);
            $count++;
        }

        $this->orderLockService->lockProcessingOrder($this->orderId);

        $this->currentOrderPayment = $this->currentOrder->getPayment();

        /** @var string|void $paymentVerified */
        $paymentVerified = $this->paymentTransaction->verifyPaymentData($params, $this->currentOrder);
        $this->processService->processTransaction($this->transactionId, $this->currentOrder, $this->orderId);
        if ($paymentVerified === 'ok') {
            $this->processService->processPayment($this->currentOrder, $this->transactionId, $this->getDetails());
            $this->processService->processInvoice($this->currentOrder);
        }
        $this->processService->processOrder($paymentVerified, $this->currentOrder);

        $this->orderLockService->unlockProcessingOrder($this->orderId);
    }
    
    /**
     * GetDetails function
     *
     * @return array
     */
    protected function getDetails(): array
    {
        return [
            'orderNo'   => $this->orderIncrementalId,
            'stamp'     => $this->paramsStamp,
            'method'    => $this->paramsMethod
        ];
    }
}
