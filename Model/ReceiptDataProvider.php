<?php
namespace Paytrail\PaymentService\Model;

use Magento\Backend\Model\UrlInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
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
use Paytrail\PaymentService\Exceptions\TransactionSuccessException;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Helper\ApiData;
use Paytrail\PaymentService\Helper\Data as paytrailHelper;
use Paytrail\PaymentService\Setup\Patch\Data\InstallPaytrail;
use Psr\Log\LoggerInterface;

/**
 * Class ReceiptDataProvider
 */
class ReceiptDataProvider
{
    public const RECEIPT_PROCESSING_CACHE_PREFIX = "receipt_processing_";
    public const PAYTRAIL_API_PAYMENT_STATUS_OK = 'ok';
    public const PAYTRAIL_API_PAYMENT_STATUS_PENDING = 'pending';
    public const PAYTRAIL_API_PAYMENT_STATUS_DELAYED = 'delayed';
    public const PAYTRAIL_API_PAYMENT_STATUS_FAIL = 'fail';

    private const CONTINUABLE_STATUSES = [
        self::PAYTRAIL_API_PAYMENT_STATUS_PENDING,
        self::PAYTRAIL_API_PAYMENT_STATUS_DELAYED,
    ];

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
     * @var |Magento\Framework\App\CacheInterface
     */
    private $cache;

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
     * ReceiptDataProvider constructor.
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
     * @param transactionBuilderInterface $transactionBuilder
     * @param Config $gatewayConfig
     * @param ApiData $apiData
     * @param LoggerInterface $logger
     * @param UrlInterface $backendUrl
     * @param OrderFactory $orderFactory
     */
    public function __construct(
        Session                        $session,
        TransactionRepositoryInterface $transactionRepository,
        OrderSender                    $orderSender,
        TransportBuilder               $transportBuilder,
        ScopeConfigInterface           $scopeConfig,
        OrderManagementInterface       $orderManagementInterface,
        OrderRepositoryInterface       $orderRepositoryInterface,
        CacheInterface                 $cache,
        InvoiceService                 $invoiceService,
        TransactionFactory             $transactionFactory,
        paytrailHelper                 $paytrailHelper,
        transactionBuilder             $transactionBuilder,
        Config                         $gatewayConfig,
        ApiData                        $apiData,
        LoggerInterface                $logger,
        UrlInterface                   $backendUrl,
        OrderFactory                   $orderFactory
    ) {
        $this->cache = $cache;
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
     * Interrogates the current payment message. If valid: updates order and saves invoice and payment data
     *
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

        // wait 3 seconds for the order cache to be cleared.
        while ($this->isOrderLocked($this->orderId) && $count < 3) {
            sleep(1);
            $count++;
        }

        $this->lockProcessingOrder($this->orderId);

        $this->currentOrderPayment = $this->currentOrder->getPayment();

        /** @var string|void $paymentVerified */
        $paymentVerified = $this->verifyPaymentData($params);
        $this->processTransaction($paymentVerified);
        if ($paymentVerified === 'ok') {
            $this->processInvoice();
        }
        $this->processOrder($paymentVerified);

        $this->unlockProcessingOrder($this->orderId);
    }

    /**
     * Add order lock to cache
     *
     * @param int $orderId
     */
    protected function lockProcessingOrder($orderId)
    {
        /** @var string $identifier */
        $identifier = self::RECEIPT_PROCESSING_CACHE_PREFIX . $orderId;

        $this->cache->save("locked", $identifier);
    }

    /**
     * Remove lock from cache
     *
     * @param int $orderId
     */
    protected function unlockProcessingOrder($orderId)
    {
        /** @var string $identifier */
        $identifier = self::RECEIPT_PROCESSING_CACHE_PREFIX . $orderId;

        $this->cache->remove($identifier);
    }

    /**
     * Check order locked state from cache.
     *
     * @param int $orderId
     * @return bool
     */
    protected function isOrderLocked($orderId)
    {
        /** @var string $identifier */
        $identifier = self::RECEIPT_PROCESSING_CACHE_PREFIX . $orderId;

        return (bool)$this->cache->load($identifier);
    }

    /**
     * Update order status based on payment status and send order confirmation email
     *
     * @param string $paymentVerified
     */
    protected function processOrder($paymentVerified)
    {
        $orderState = $this->gatewayConfig->getDefaultOrderStatus();

        if ($paymentVerified === 'ok') {
            $this->currentOrder->setState($orderState)->setStatus($orderState);
            $this->currentOrder->addCommentToStatusHistory(__('Payment has been completed'));
        } else {
            $this->currentOrder->setState(InstallPaytrail::ORDER_STATE_CUSTOM_CODE);
            $this->currentOrder->setStatus(InstallPaytrail::ORDER_STATUS_CUSTOM_CODE);
            $this->currentOrder->addCommentToStatusHistory(__('Pending payment from Paytrail Payment Service'));
        }

        $this->orderRepositoryInterface->save($this->currentOrder);
        $this->sendEmail();
    }

    /**
     * If order can be invoiced, create a new invoice using transaction save.
     *
     * @throws CheckoutException
     */
    protected function processInvoice()
    {
        if ($this->currentOrder->canInvoice()) {
            try {
                /** @var /Magento/Sales/Api/Data/InvoiceInterface|/Magento/Sales/Model/Order/Invoice $invoice */
                $invoice = $this->invoiceService->prepareInvoice($this->currentOrder); //TODO: catch \InvalidArgumentException which extends \Exception
                $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
                $invoice->setTransactionId($this->currentOrderPayment->getLastTransId());
                $invoice->register();
                /** @var /Magento/Framework/DB/Transaction $transactionSave */
                $transactionSave = $this->transactionFactory->create();
                $transactionSave->addObject(
                    $invoice
                )->addObject(
                    $this->currentOrder
                )->save();
            } catch (\Exception $exception) {
                $this->paytrailHelper->processError($exception->getMessage());
            }
        }
    }

    /**
     * Send email message to admin that an order requires their attention
     *
     * If an order got a failed payment status from Payment api followed up by "payment ok" status later admin user
     * needs to be informed so that manual "restore order" action can be performed in admin.
     *
     * @throws MailException
     * @throws LocalizedException
     */
    protected function notifyCanceledOrder()
    {
        if (filter_var($this->gatewayConfig->getNotificationEmail(), FILTER_VALIDATE_EMAIL)) {
            $transport = $this->transportBuilder
                ->setTemplateIdentifier('restore_order_notification')
                ->setTemplateOptions([
                    'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => \Magento\Store\Model\Store::DEFAULT_STORE_ID
                ])
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
     * Get array of payment transactional information based on current request
     *
     * @return array
     */
    protected function getDetails()
    {
        return [
            'orderNo' => $this->orderIncrementalId,
            'stamp' => $this->paramsStamp,
            'method' => $this->paramsMethod,
        ];
    }

    /**
     * Load order by current increment id.
     *
     * @return \Magento\Sales\Model\Order
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
     * Validate that incoming request has correct Hmac and payment status
     *
     * @param string[] $params
     *
     * @return string|void
     * @throws CheckoutException thrown if payment had status "fail"
     * @throws LocalizedException Thrown if errors happen in cancel order.
     */
    protected function verifyPaymentData($params)
    {
        $status = $params['checkout-status'];
        $verifiedPayment = $this->apiData->validateHmac($params, $params['signature']);

        if ($verifiedPayment
            && ($status === self::PAYTRAIL_API_PAYMENT_STATUS_OK
                || $status === self::PAYTRAIL_API_PAYMENT_STATUS_PENDING
                || $status === self::PAYTRAIL_API_PAYMENT_STATUS_DELAYED
            )) {
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
     * Load existing payment transaction or generate a new one based on the payment status.
     *
     * @return \Magento\Sales\Model\Order\Payment\Transaction|bool
     * @throws CheckoutException thrown on unexpected load errors.
     */
    protected function loadTransaction()
    {
        $transaction = false;
        try {
            $transaction = $this->transactionRepository->getByTransactionId(
                $this->transactionId,
                $this->currentOrder->getPayment()->getId(),
                $this->orderId
            );
        } catch (InputException $e) {
            $this->paytrailHelper->processError($e->getMessage());
        }

        return $transaction;
    }

    /**
     * Validates ongoing transaction against the information in previous transaction
     *
     * Validate transaction id is the same as old id and that previous transaction did not finish the payment
     * Note that for backwards compatibility if transaction is missing api_status field, assume completed payment.
     *
     * @param \Magento\Sales\Model\Order\Payment\Transaction|bool $transaction
     *
     * @throws \Paytrail\PaymentService\Exceptions\TransactionSuccessException thrown if previous transaction got "ok"
     * @throws CheckoutException thrown if multiple transaction ids are present.
     */
    protected function validateOldTransaction($transaction)
    {
        if ($transaction) {
            if ($transaction->getTxnId() !== $this->transactionId) {
                $this->paytrailHelper->processError('Payment failed, multiple transactions detected');
            }

            $details = $transaction->getAdditionalInformation(Transaction::RAW_DETAILS);
            if (isset($details['api_status']) && in_array($details['api_status'], self::CONTINUABLE_STATUSES)) {
                return;
            }

            // transaction was already completed with 'Ok' status.
            $this->paytrailHelper->processSuccess();
        }
    }

    /**
     * Create a new transaction and save it to database or update the old one.
     *
     * @param string $paymentStatus
     *
     * @throws CheckoutException thrown if multiple transactions are present
     * @throws TransactionSuccessException thrown if previous transaction got "ok" status from Paytrail
     * @throws LocalizedException thrown if payment details array is malformed or email notification failed
     */
    protected function processTransaction(string $paymentStatus)
    {
        $oldTransaction = $this->loadTransaction();
        $this->validateOldTransaction($oldTransaction);
        $oldStatus = false;
        $paymentDetails = $this->getDetails();
        $paymentDetails['api_status'] = $paymentStatus;

        if ($oldTransaction) {
            $transaction = $this->updateOldTransaction($oldTransaction, $paymentDetails);

            // Backwards compatibility: If transaction exists without api_status, assume OK status since
            // only 'ok' status could create transactions in old version.
            $oldStatus = isset($oldTransaction->getAdditionalInformation(Transaction::RAW_DETAILS)['api_status'])
                ? $oldTransaction->getAdditionalInformation(Transaction::RAW_DETAILS)['api_status']
                : 'ok';
        } else {
            $transaction = $this->addPaymentTransaction(
                $this->currentOrder,
                $this->transactionId,
                $paymentDetails
            );
        }

        // Only append transaction comments to orders if the payment status changes
        if ($oldStatus !== $paymentStatus) {
            $this->currentOrderPayment->addTransactionCommentsToOrder(
                $transaction,
                __('Paytrail Api - New payment status: "%status"', ['status' => $paymentStatus])
            );
            $this->currentOrderPayment->setLastTransId($this->transactionId);
        }

        if ($this->currentOrder->getStatus() == 'canceled') {
            $this->notifyCanceledOrder();
        }
    }

    /**
     * Create new payment transaction
     *
     * @param \Magento\Sales\Model\Order $order
     * @param string $transactionId
     * @param array $details
     * @return \Magento\Sales\Api\Data\TransactionInterface
     */
    protected function addPaymentTransaction(
        \Magento\Sales\Model\Order $order,
        $transactionId,
        array $details = []
    ) {
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
     * If automatic order cancellation is enabled. Cancel the order on first "Failed" payment response.
     *
     * @param int $orderId
     * @return void
     * @throws CheckoutException
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

    /**
     * Conditionally send email if it was not sent before
     *
     * @return void
     */
    private function sendEmail(): void
    {
        if ($this->currentOrder->getEmailSent()) {
            return; // only send confirmation email once.
        }

        try {
            $this->orderSender->send($this->currentOrder);
        } catch (\Exception $e) {
            $this->logger->error(\sprintf(
                'Paytrail: Order email sending failed: %s',
                $e->getMessage()
            ));
        }
    }

    /**
     * @param bool|Transaction $oldTransaction
     * @param array $paymentDetails
     * @return Transaction
     * @throws LocalizedException
     */
    private function updateOldTransaction(bool|Transaction $oldTransaction, array $paymentDetails): Transaction
    {
        $transaction = $oldTransaction->setAdditionalInformation(Transaction::RAW_DETAILS, $paymentDetails);
        $this->transactionRepository->save($transaction);

        return $transaction;
    }
}
