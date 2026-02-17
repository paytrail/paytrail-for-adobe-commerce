<?php

namespace Paytrail\PaymentService\Model;

use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Model\PaymentMethod\OrderPaymentMethodData;
use Paytrail\PaymentService\Model\Receipt\LoadService;
use Paytrail\PaymentService\Model\Receipt\PaymentTransaction;
use Paytrail\PaymentService\Model\Receipt\ProcessService;

class ReceiptDataProvider
{
    /**
     * @var string
     */
    private $orderIncrementalId;

    /**
     * @var string
     */
    private $transactionId;

    /**
     * @var string
     */
    private $paramsStamp;

    /**
     * @var string
     */
    private $paramsMethod;

    /**
     * @var Order
     */
    private $currentOrder;

    /**
     * @var string
     */
    private $orderId;

    /**
     * ReceiptDataProvider constructor.
     *
     * @param Session $session
     * @param Config $gatewayConfig
     * @param ProcessService $processService
     * @param LoadService $loadService
     * @param PaymentTransaction $paymentTransaction
     * @param FinnishReferenceNumber $referenceNumber
     * @param OrderPaymentMethodData $orderPaymentMethodData
     */
    public function __construct(
        private Session $session,
        private Config $gatewayConfig,
        private ProcessService $processService,
        private LoadService $loadService,
        private PaymentTransaction $paymentTransaction,
        private FinnishReferenceNumber $referenceNumber,
        private OrderPaymentMethodData $orderPaymentMethodData
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

        /** @var string|void $paymentVerified */
        $paymentVerified = $this->paymentTransaction->verifyPaymentData($params, $this->currentOrder);

        // Set payment method data from checkout-provider when payment is verified
        // This handles the case when payment method selection is on a separate page (SkipBankSelection enabled)
        $this->orderPaymentMethodData->setPaymentMethodDataFromCallback(
            $this->currentOrder,
            $this->paramsMethod,
            false // Don't save order here, it will be saved in processOrder
        );

        $this->processService->processTransaction(
            $paymentVerified,
            $this->transactionId,
            $this->currentOrder,
            $this->orderId,
            $this->getDetails($paymentVerified)
        );
        if ($paymentVerified === 'ok') {
            $this->processService
                ->processPayment($this->currentOrder, $this->transactionId, $this->getDetails($paymentVerified));
            $this->processService->processInvoice($this->currentOrder);
        }
        $this->processService->processOrder($paymentVerified, $this->currentOrder);
    }

    /**
     * Get details.
     *
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
