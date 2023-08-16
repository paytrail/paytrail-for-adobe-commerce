<?php

namespace Paytrail\PaymentService\Model\Receipt;

use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Service\InvoiceService;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Exceptions\TransactionSuccessException;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Helper\Data as PaytrailHelper;
use Paytrail\PaymentService\Logger\PaytrailLogger;
use Paytrail\PaymentService\Setup\Patch\Data\InstallPaytrail;
use Psr\Log\LoggerInterface;

class ProcessService
{
    /**
     * @param Config $gatewayConfig
     * @param OrderRepositoryInterface $orderRepositoryInterface
     * @param OrderSender $orderSender
     * @param LoggerInterface $logger
     * @param InvoiceService $invoiceService
     * @param Payment $currentOrderPayment
     * @param TransactionFactory $transactionFactory
     * @param PaytrailHelper $paytrailHelper
     */
    public function __construct(
        private Config                   $gatewayConfig,
        private OrderRepositoryInterface $orderRepositoryInterface,
        private OrderSender              $orderSender,
        private LoggerInterface          $logger,
        private InvoiceService           $invoiceService,
        private Payment                  $currentOrderPayment,
        private TransactionFactory       $transactionFactory,
        private PaytrailHelper           $paytrailHelper,
        private LoadService $loadService,
        private PaymentTransaction $paymentTransaction,
        private CancelOrderService $cancelOrderService,
        private PaytrailLogger $paytrailLogger
    ) {
    }

    /**
     * ProcessOrder function
     *
     * @param $paymentVerified
     * @param $currentOrder
     * @return $this
     */
    public function processOrder($paymentVerified, $currentOrder)
    {
        $orderState = $this->gatewayConfig->getDefaultOrderStatus();

        if ($paymentVerified === 'ok') {
            $currentOrder->setState($orderState)->setStatus($orderState);
            $currentOrder->addCommentToStatusHistory(__('Payment has been completed'));
        } else {
            $currentOrder->setState(InstallPaytrail::ORDER_STATE_CUSTOM_CODE);
            $currentOrder->setStatus(InstallPaytrail::ORDER_STATUS_CUSTOM_CODE);
            $currentOrder->addCommentToStatusHistory(__('Pending payment from Paytrail Payment Service'));
        }

        $this->orderRepositoryInterface->save($currentOrder);

        try {
            $this->orderSender->send($currentOrder);
        } catch (\Exception $e) {
            $this->logger->error(\sprintf(
                'Paytrail: Order email sending failed: %s',
                $e->getMessage()
            ));
        }

        return $this;
    }

    /**
     * ProcessInvoice function
     *
     * @param $currentOrder
     * @return void
     * @throws \Paytrail\PaymentService\Exceptions\CheckoutException
     */
    public function processInvoice($currentOrder)
    {
        if ($currentOrder->canInvoice()) {
            try {
                /** @var /Magento/Sales/Api/Data/InvoiceInterface|/Magento/Sales/Model/Order/Invoice $invoice */
                $invoice = $this->invoiceService->prepareInvoice($currentOrder);
                //TODO: catch \InvalidArgumentException which extends \Exception
                $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
                $invoice->setTransactionId($this->currentOrderPayment->getLastTransId());
                $invoice->register();
                /** @var /Magento/Framework/DB/Transaction $transactionSave */
                $transactionSave = $this->transactionFactory->create();
                $transactionSave->addObject(
                    $invoice
                )->addObject(
                    $currentOrder
                )->save();
            } catch (\Exception $exception) {
                $this->processError($exception->getMessage());
            }
        }
    }

    /**
     * ProcessPayment function
     *
     * @param $currentOrder
     * @param $transactionId
     * @param $details
     * @return void
     */
    public function processPayment($currentOrder, $transactionId, $details)
    {
        $transaction = $this->paymentTransaction->addPaymentTransaction($currentOrder, $transactionId, $details);

        $this->currentOrderPayment->setOrder($currentOrder);
        $this->currentOrderPayment->addTransactionCommentsToOrder($transaction, '');
        $this->currentOrderPayment->setLastTransId($transactionId);

        if ($currentOrder->getStatus() == 'canceled') {
            $this->cancelOrderService->notifyCanceledOrder($currentOrder);
        }
    }

    /**
     * ProcessExistingTransaction function
     *
     * @param $transaction
     * @return void
     * @throws \Paytrail\PaymentService\Exceptions\TransactionSuccessException
     */
    protected function processExistingTransaction($transaction)
    {
        $details = $transaction->getAdditionalInformation(Transaction::RAW_DETAILS);
        if (is_array($details)) {
            $this->processSuccess();
        }
    }

    /**
     * ProcessTransaction function
     *
     * @param $transactionId
     * @param $currentOrder
     * @param $orderId
     * @return bool
     * @throws \Paytrail\PaymentService\Exceptions\CheckoutException
     * @throws \Paytrail\PaymentService\Exceptions\TransactionSuccessException
     */
    public function processTransaction($transactionId, $currentOrder, $orderId): bool
    {
        $transaction = $this->loadService->loadTransaction($transactionId, $currentOrder, $orderId);
        if ($transaction) {
            $this->processExistingTransaction($transaction);
            $this->processError('Payment failed');
        }
        return true;
    }

    /**
     * Process error
     *
     * @param string $errorMessage
     *
     * @throws CheckoutException
     */
    public function processError($errorMessage)
    {
        $this->paytrailLogger->logData(\Monolog\Logger::ERROR, $errorMessage);
        throw new CheckoutException(__($errorMessage));
    }

    /**
     * Process success
     *
     * @throws TransactionSuccessException
     */
    public function processSuccess(): void
    {
        throw new TransactionSuccessException(__('Success'));
    }
}
