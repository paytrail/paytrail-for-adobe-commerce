<?php

namespace Paytrail\PaymentService\Model;

use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Helper\ApiData;
use Paytrail\PaymentService\Helper\Data as paytrailHelper;
use Paytrail\PaymentService\Model\Email\Order\PendingOrderEmailConfirmation;
use Paytrail\PaymentService\Model\Receipt\LoadService;
use Paytrail\PaymentService\Model\Receipt\PaymentTransaction;
use Paytrail\PaymentService\Model\Receipt\ProcessService;
use Paytrail\PaymentService\Setup\Patch\Data\InstallPaytrail;
use Psr\Log\LoggerInterface;

class ReceiptDataProvider
{
    /**
     * ReceiptDataProvider constructor.
     *
     * @param Session $session
     * @param paytrailHelper $paytrailHelper
     * @param Config $gatewayConfig
     * @param ProcessService $processService
     * @param LoadService $loadService
     * @param PaymentTransaction $paymentTransaction
     */
    public function __construct(
        private Session $session,
        private PaytrailHelper $paytrailHelper,
        private Config $gatewayConfig,
        private ProcessService $processService,
        private LoadService $loadService,
        private PaymentTransaction $paymentTransaction,
        private FinnishReferenceNumber $referenceNumber
    ) {
    }

    /**
     * Execute function
     *
     * @param array $params
     *
     * @throws CheckoutException
     * @throws LocalizedException
     */
    public function execute(array $params)
    {
        if ($this->gatewayConfig->getGenerateReferenceForOrder()) {
            $this->orderIncrementalId = $this->referenceNumber->getIdFromOrderReferenceNumber(
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

        $this->currentOrderPayment = $this->currentOrder->getPayment();

        /** @var string|void $paymentVerified */
        $paymentVerified = $this->paymentTransaction->verifyPaymentData($params, $this->currentOrder);
        $this->processService->processTransaction($this->transactionId, $this->currentOrder, $this->orderId);
        if ($paymentVerified === 'ok') {
            $this->processService->processPayment($this->currentOrder, $this->transactionId, $this->getDetails($paymentVerified));
            $this->processService->processInvoice($this->currentOrder);
        }
        $this->processService->processOrder($paymentVerified, $this->currentOrder);
    }

    /**
     * @param string $paymentStatus
     * GetDetails function
     *
     * @return array
     */
    protected function getDetails($paymentStatus): array
    {
        return [
            'orderNo'    => $this->orderIncrementalId,
            'stamp'      => $this->paramsStamp,
            'method'     => $this->paramsMethod,
            'api_status' => $paymentStatus
        ];
    }
}
