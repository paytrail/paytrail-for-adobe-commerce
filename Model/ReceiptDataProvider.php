<?php
namespace Paytrail\PaymentService\Model;

use Magento\Backend\Model\UrlInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\Builder as transactionBuilder;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface as transactionBuilderInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Helper\ApiData;
use Paytrail\PaymentService\Helper\Data as paytrailHelper;
use Paytrail\PaymentService\Setup\Patch\Data\InstallPaytrail;
use Psr\Log\LoggerInterface;

class ReceiptDataProvider
{
    /**
     * @var Session
     */
    protected $session;

    /**
     * @var TransactionRepositoryInterface
     */
    protected $transactionRepository;

    /**
     * @var OrderSender
     */
    protected $orderSender;

    /**
     * @var TransportBuilder
     */
    protected $transportBuilder;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var OrderManagementInterface
     */
    protected $orderManagementInterface;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepositoryInterface;

    /**
     * @var InvoiceService
     */
    protected $invoiceService;

    /**
     * @var TransactionFactory
     */
    protected $transactionFactory;

    /**
     * @var paytrailHelper
     */
    protected $paytrailHelper;

    /**
     * @var transactionBuilderInterface
     */
    protected $transactionBuilder;

    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $currentOrder;

    /**
     * @var \Magento\Sales\Model\Order\Payment
     */
    protected $currentOrderPayment;

    /**
     * @var null|int
     */
    protected $orderId;

    /**
     * @var null|string
     */
    protected $orderIncrementalId;

    /**
     * @var null|string
     */
    protected $transactionId;

    /**
     * @var null|string
     */
    protected $paramsStamp;

    /**
     * @var null|string
     */
    protected $paramsMethod;
    /**
     * @var Config
     */
    private $gatewayConfig;
    /**
     * @var ApiData
     */
    private $apiData;
    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * @var UrlInterface
     */
    private $backendUrl;

    /**
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * @param Session $session
     * @param TransactionRepositoryInterface $transactionRepository
     * @param OrderSender $orderSender
     * @param TransportBuilder $transportBuilder
     * @param ScopeConfigInterface $scopeConfig
     * @param OrderManagementInterface $orderManagementInterface
     * @param OrderRepositoryInterface $orderRepositoryInterface
     * @param CacheInterface $cache
     * @param InvoiceService $invoiceService
     * @param TransactionFactory $transactionFactory
     * @param paytrailHelper $paytrailHelper
     * @param transactionBuilder $transactionBuilder
     * @param Config $gatewayConfig
     * @param ApiData $apiData
     * @param LoggerInterface $logger
     * @param UrlInterface $backendUrl
     * @param OrderFactory $orderFactory
     * @param Receipt\OrderLockService $orderLockService
     */
    public function __construct(
        Session $session,
        TransactionRepositoryInterface $transactionRepository,
        OrderSender $orderSender,
        TransportBuilder $transportBuilder,
        ScopeConfigInterface $scopeConfig,
        OrderManagementInterface $orderManagementInterface,
        OrderRepositoryInterface $orderRepositoryInterface,
        InvoiceService $invoiceService,
        TransactionFactory $transactionFactory,
        paytrailHelper $paytrailHelper,
        transactionBuilder $transactionBuilder,
        Config $gatewayConfig,
        ApiData $apiData,
        LoggerInterface $logger,
        UrlInterface $backendUrl,
        OrderFactory $orderFactory,
        private \Paytrail\PaymentService\Model\Receipt\OrderLockService $orderLockService,
        private \Paytrail\PaymentService\Model\Receipt\ProcessService $processService
    ) {
        $this->session = $session;
        $this->transactionRepository = $transactionRepository;
        $this->orderSender = $orderSender;
        $this->transportBuilder = $transportBuilder;
        $this->scopeConfig = $scopeConfig;
        $this->orderManagementInterface = $orderManagementInterface;
        $this->orderRepositoryInterface = $orderRepositoryInterface;
        $this->invoiceService = $invoiceService;
        $this->transactionFactory = $transactionFactory;
        $this->paytrailHelper = $paytrailHelper;
        $this->transactionBuilder = $transactionBuilder;
        $this->gatewayConfig = $gatewayConfig;
        $this->apiData = $apiData;
        $this->logger = $logger;
        $this->backendUrl = $backendUrl;
        $this->orderFactory = $orderFactory;
    }

    /**
     * @param array $params
     * @throws CheckoutException
     * @throws LocalizedException
     */
    public function execute(array $params)
    {
        if ($this->gatewayConfig->getGenerateReferenceForOrder()) {
            $this->orderIncrementalId
                = $this->paytrailHelper->getIdFromOrderReferenceNumber(
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

        $this->currentOrder = $this->loadOrder();
        $this->orderId = $this->currentOrder->getId();

        /** @var int $count */
        $count = 0;

        while ($this->orderLockService->isOrderLocked($this->orderId) && $count < 3) {
            sleep(1);
            $count++;
        }

        $this->orderLockService->lockProcessingOrder($this->orderId);

        $this->currentOrderPayment = $this->currentOrder->getPayment();

        /** @var string|void $paymentVerified */
        $paymentVerified = $this->verifyPaymentData($params);
        $this->processService->processTransaction($this->transactionId, $this->currentOrder, $this->orderId);
        if ($paymentVerified === 'ok') {
            $this->processPayment();
            $this->processService->processInvoice($this->currentOrder);
        }
        $this->processService->processOrder($paymentVerified, $this->currentOrder);

        $this->orderLockService->unlockProcessingOrder($this->orderId);
    }

    protected function processPayment()
    {
        $transaction = $this->addPaymentTransaction($this->currentOrder, $this->transactionId, $this->getDetails());

        $this->currentOrderPayment->addTransactionCommentsToOrder($transaction, '');
        $this->currentOrderPayment->setLastTransId($this->transactionId);

        if ($this->currentOrder->getStatus() == 'canceled') {
            $this->notifyCanceledOrder();
        }
    }

    /**
     * notify canceled order
     */
    protected function notifyCanceledOrder()
    {
        if (filter_var($this->gatewayConfig->getNotificationEmail(), FILTER_VALIDATE_EMAIL)) {
            $transport = $this->transportBuilder
                ->setTemplateIdentifier('restore_order_notification')
                ->setTemplateOptions(['area' => \Magento\Framework\App\Area::AREA_FRONTEND, 'store' => \Magento\Store\Model\Store::DEFAULT_STORE_ID])
                ->setTemplateVars([
                    'order' => [
                        'increment' => $this->currentOrder->getIncrementId(),
                        'url' => $this->backendUrl->getUrl(
                            'sales/order/view',
                            ['order_id' => $this->currentOrder->getId()]
                        )
                    ]
                ])
                ->setFrom([
                    'name' => $this->scopeConfig->getValue('general/store_information/name') . ' - Magento',
                    'email' => $this->scopeConfig->getValue('trans_email/ident_general/email'),
                ])->addTo([
                    $this->gatewayConfig->getNotificationEmail()
                ])->getTransport();
            $transport->sendMessage();
        }
    }

    /**
     * @return array
     */
    protected function getDetails()
    {
        return [
            'orderNo'   => $this->orderIncrementalId,
            'stamp'     => $this->paramsStamp,
            'method'    => $this->paramsMethod
        ];
    }

    /**
     * @return mixed
     * @throws CheckoutException
     */
    protected function loadOrder()
    {
        $order = $this->orderFactory->create()->loadByIncrementId($this->orderIncrementalId);
        if (!$order->getId()) {
            $this->paytrailHelper->processError('Order not found');
        }
        return $order;
    }

    /**
     * @param string[] $params
     * @throws LocalizedException
     * @throws CheckoutException
     * @return string|void
     */
    protected function verifyPaymentData($params)
    {
        $status = $params['checkout-status'];
        $verifiedPayment = $this->apiData->validateHmac($params, $params['signature']);

        if ($verifiedPayment && ($status === 'ok' || $status == 'pending' || $status == 'delayed')) {
            return $status;
        } else {
            $this->currentOrder->addCommentToStatusHistory(__('Failed to complete the payment.'));
            $this->orderRepositoryInterface->save($this->currentOrder);
            $this->cancelOrderById($this->currentOrder->getId());
            $this->paytrailHelper->processError(
                'Failed to complete the payment. Please try again or contact the customer service.'
            );
        }
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param $transactionId
     * @param array $details
     * @return \Magento\Sales\Api\Data\TransactionInterface
     */
    protected function addPaymentTransaction(\Magento\Sales\Model\Order $order, $transactionId, array $details = [])
    {
        /** @var \Magento\Framework\DataObject|\Magento\Sales\Api\Data\OrderPaymentInterface |mixed|null $payment */
        $payment = $order->getPayment();

        /** @var \Magento\Sales\Api\Data\TransactionInterface $transaction */
        $transaction = $this->transactionBuilder
            ->setPayment($payment)->setOrder($order)
            ->setTransactionId($transactionId)
            ->setAdditionalInformation([Transaction::RAW_DETAILS => (array) $details])
            ->setFailSafe(true)
            ->build(Transaction::TYPE_CAPTURE);
        $transaction->setIsClosed(0);
        return $transaction;
    }

    /**
     * @param int $orderId
     * @return void
     */
    private function cancelOrderById($orderId): void
    {
        if ($this->gatewayConfig->getCancelOrderOnFailedPayment()) {
            try {
                $this->orderManagementInterface->cancel($orderId);
            } catch (\Exception $e) {
                $this->logger->critical(sprintf(
                    'Paytrail exception during order cancel: %s,\n error trace: %s',
                    $e->getMessage(),
                    $e->getTraceAsString()
                ));

                // Mask and throw end-user friendly exception
                throw new CheckoutException(__(
                    'Error while cancelling order. Please contact customer support with order id: %id to release discount coupons.',
                    [ 'id'=> $orderId ]
                ));
            }
        }
    }
}
