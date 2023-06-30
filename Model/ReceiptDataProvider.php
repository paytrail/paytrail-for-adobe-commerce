<?php

namespace Paytrail\PaymentService\Model;

use Magento\Backend\Model\UrlInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\CacheInterface;
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
use Paytrail\PaymentService\Model\Email\Order\PendingOrderEmailConfirmation;
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
     * @var \Paytrail\PaymentService\Model\FinnishReferenceNumber
     */
    protected FinnishReferenceNumber $finnishReferenceNumber;

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
     * @var bool
     */
    private $skipHmac;

    /**
     * @var PendingOrderEmailConfirmation
     */
    private $pendingOrderEmail;

    /**
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * ReceiptDataProvider constructor.
     *
     * @param Session                                                $session
     * @param TransactionRepositoryInterface                         $transactionRepository
     * @param OrderSender                                            $orderSender
     * @param TransportBuilder                                       $transportBuilder
     * @param ScopeConfigInterface                                   $scopeConfig
     * @param OrderManagementInterface                               $orderManagementInterface
     * @param OrderRepositoryInterface                               $orderRepositoryInterface
     * @param CacheInterface                                         $cache
     * @param InvoiceService                                         $invoiceService
     * @param TransactionFactory                                     $transactionFactory
     * @param paytrailHelper                                         $paytrailHelper
     * @param \Magento\Sales\Model\Order\Payment\Transaction\Builder $transactionBuilder
     * @param Config                                                 $gatewayConfig
     * @param ApiData                                                $apiData
     * @param LoggerInterface                                        $logger
     * @param UrlInterface                                           $backendUrl
     * @param OrderFactory                                           $orderFactory
     * @param PendingOrderEmailConfirmation                          $pendingOrderEmail
     * @param \Paytrail\PaymentService\Model\FinnishReferenceNumber  $finnishReferenceNumber
     * @param boolean                                                $skipHmac
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
        OrderFactory                   $orderFactory,
        PendingOrderEmailConfirmation  $pendingOrderEmail,
        FinnishReferenceNumber $finnishReferenceNumber,
        $skipHmac = false
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
        $this->skipHmac = $skipHmac;
        $this->pendingOrderEmail = $pendingOrderEmail;
        $this->finnishReferenceNumber = $finnishReferenceNumber;
    }

    /**
     * @param array $params
     *
     * @throws CheckoutException
     * @throws LocalizedException
     * @throws \Exception
     */
    public function execute(array $params)
    {
        if ($this->gatewayConfig->getGenerateReferenceForOrder()) {
            $this->orderIncrementalId
                = $this->finnishReferenceNumber->getIdFromOrderReferenceNumber(
                    $params["checkout-reference"]
                );
        } else {
            $this->orderIncrementalId
                = $params["checkout-reference"];
        }
        $this->transactionId = $params["checkout-transaction-id"];
        $this->paramsStamp = $params['checkout-stamp'];
        $this->paramsMethod = $params['checkout-provider'];

        $this->session->unsCheckoutRedirectUrl();

        $this->currentOrder = $this->loadOrder();
        $this->orderId = $this->currentOrder->getId();

        /** @var int $count */
        $count = 0;

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
     * @param int $orderId
     */
    protected function lockProcessingOrder($orderId)
    {
        /** @var string $identifier */
        $identifier = self::RECEIPT_PROCESSING_CACHE_PREFIX . $orderId;

        $this->cache->save("locked", $identifier);
    }

    /**
     * @param int $orderId
     */
    protected function unlockProcessingOrder($orderId)
    {
        /** @var string $identifier */
        $identifier = self::RECEIPT_PROCESSING_CACHE_PREFIX . $orderId;

        $this->cache->remove($identifier);
    }

    /**
     * @param int $orderId
     * @return bool
     */
    protected function isOrderLocked($orderId)
    {
        /** @var string $identifier */
        $identifier = self::RECEIPT_PROCESSING_CACHE_PREFIX . $orderId;

        return $this->cache->load($identifier) ? true : false;
    }

    /**
     * @param $paymentVerified
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

        try {
            if (!$this->pendingOrderEmail->isPendingOrderEmailEnabled()) {
                $this->orderSender->send($this->currentOrder);
            }
        } catch (\Exception $e) {
            $this->logger->error(\sprintf(
                'Paytrail: Order email sending failed: %s',
                $e->getMessage()
            ));
        }
    }

    /**
     * process invoice
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
     * notify canceled order
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
     * @param string $paymentStatus
     * @return array
     */
    protected function getDetails($paymentStatus)
    {
        return [
            'orderNo'    => $this->orderIncrementalId,
            'stamp'      => $this->paramsStamp,
            'method'     => $this->paramsMethod,
            'api_status' => $paymentStatus
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
     *
     * @throws LocalizedException
     * @throws CheckoutException
     * @return string|void
     */
    protected function verifyPaymentData($params)
    {
        $status = $params['checkout-status'];

        /**
         * When paying with payment token, such as a card saved to vault. The HMAC validation is done by the php-sdk
         * directly during the payment post. When this happens the signature parameter is not passed into subsquent
         * logic. Making Hmac validation here impossible. This forces the skip implementation for Token payments.
         *
         * @see \Paytrail\SDK\Client::createCitPayment
         */
        $verifiedPayment = $this->skipHmac ?: $this->apiData->validateHmac($params, $params['signature']);

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
     * Validate that payment process is allowed to continue past an existing transaction
     *
     * @return bool|\Magento\Sales\Model\Order\Payment\Transaction
     * @throws CheckoutException
     */
    protected function loadTransaction()
    {
        /** @var bool|mixed $transaction */
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
     * @param $transaction
     */
    protected function validateOldTransaction($transaction)
    {
        if ($transaction) {
            if ($transaction->getTxnId() !== $this->transactionId) {
                $this->paytrailHelper->processError('Payment failed, multiple transactions detected');
            }

            $details = $transaction->getAdditionalInformation(Transaction::RAW_DETAILS);
            if (is_array($details)
                && (!isset($details['api_status']) || $details['api_status'] === self::PAYTRAIL_API_PAYMENT_STATUS_OK)
            ) {
                // After a singular "ok" response, Magento needs to ignore following callbacks.
                $this->paytrailHelper->processSuccess();
            }
        }
    }

    /**
     * @param string $paymentStatus
     *
     * @throws CheckoutException
     */
    protected function processTransaction(string $paymentStatus)
    {
        $oldTransaction = $this->loadTransaction();
        $this->validateOldTransaction($oldTransaction);

        if ($oldTransaction) {
            $transaction = $this->updateTransaction(
                $oldTransaction,
                $this->getDetails($paymentStatus)
            );
        } else {
            $transaction = $this->addPaymentTransaction(
                $this->currentOrder,
                $this->transactionId,
                $this->getDetails($paymentStatus)
            );
        }

        $this->currentOrderPayment->addTransactionCommentsToOrder($transaction, '');
        $this->currentOrderPayment->setLastTransId($this->transactionId);

        if ($this->currentOrder->getStatus() == 'canceled') {
            $this->notifyCanceledOrder();
        }
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param $transactionId
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
            ->setAdditionalInformation([Transaction::RAW_DETAILS => (array)$details])
            ->setFailSafe(true)
            ->build(Transaction::TYPE_CAPTURE);
        $transaction->setIsClosed(0);
        return $transaction;
    }

    /**
     * @param \Magento\Sales\Api\Data\TransactionInterface $oldTransaction
     * @param array $details
     * @return mixed
     */
    private function updateTransaction($oldTransaction, array $details)
    {
        $oldTransaction->setAdditionalInformation(Transaction::RAW_DETAILS, $details);

        return $oldTransaction;
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
                    ['id' => $orderId]
                ));
            }
        }
    }
}
