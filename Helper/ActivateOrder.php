<?php
namespace Paytrail\PaymentService\Helper;

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\DB\TransactionFactory;
use Psr\Log\LoggerInterface;

/**
 * Class ActivateOrder
 */
class ActivateOrder
{
    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;
    /**
     * @var TransactionRepositoryInterface
     */
    private $transactionRepository;
    /**
     * @var InvoiceService
     */
    private $invoiceService;
    /**
     * @var TransactionFactory
     */
    private $transactionFactory;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * ActivateOrder constructor.
     * @param OrderRepositoryInterface $orderRepository
     * @param TransactionRepositoryInterface $transactionRepository
     * @param InvoiceService $invoiceService
     * @param TransactionFactory $transactionFactory
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        TransactionRepositoryInterface $transactionRepository,
        InvoiceService $invoiceService,
        TransactionFactory $transactionFactory,
        LoggerInterface $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->transactionRepository = $transactionRepository;
        $this->invoiceService = $invoiceService;
        $this->transactionFactory = $transactionFactory;
        $this->logger = $logger;
    }

    /**
     * @param $orderId
     * @throws AlreadyExistsException
     */
    public function activateOrder($orderId)
    {
        $order = $this->orderRepository->get($orderId);

        /**
         * Loop through order items and set canceled items as ordered
         */
        foreach ($order->getItems() as $item) {
            $item->setQtyCanceled(0);
        }

        $this->orderRepository->save($order);
        $this->processInvoice($order);
    }

    /**
     * @param $orderId
     * @return bool
     */
    public function isCanceled($orderId)
    {
        $order = $this->orderRepository->get($orderId);
        $i = 0;

        foreach ($order->getItems() as $item) {
            if ($item->getQtyCanceled() > 0) {
                $i++;
            }
        }

        $transactionId = $this->getCaptureTransaction($order);

        if ($i > 0 && $transactionId) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $order
     * @return mixed
     * @throws InputException
     */
    protected function getCaptureTransaction($order)
    {
        $transactionId = false;
        $paymentId =  $order->getPayment()->getId();
        /* For backwards compatibility, e.g. Magento 2.2.9 requires 3 parameters. */
        $transaction = $this->transactionRepository->getByTransactionType('capture', $paymentId, $order->getId());
        if ($transaction) {
            $transactionId = $transaction->getTransactionId();
        }
        return $transactionId;
    }

    /**
     * @param $order
     * @throws InputException
     * @throws \Exception
     */
    protected function processInvoice($order)
    {
        $transactionId = $this->getCaptureTransaction($order);

        if ($order->canInvoice()) {
            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
            $invoice->setTransactionId($transactionId);
            $invoice->register();
            $this->saveInvoiceAndOrder($invoice, $order);
        }
    }

    /**
     * @param \Magento\Sales\Api\Data\InvoiceInterface $invoice
     * @param $order
     * @return void
     * @throws LocalizedException
     */
    private function saveInvoiceAndOrder(\Magento\Sales\Api\Data\InvoiceInterface $invoice, $order): void
    {
        try {
            /** @var \Magento\Framework\DB\Transaction $transactionSave */
            $transactionSave = $this->transactionFactory->create();
            $transactionSave->addObject(
                $invoice
            )->addObject(
                $order
            )->save();
        } catch (\Exception $e) {
            $message = __(
                'Paytrail unable to save re-active order from admin: %error',
                ['error' => $e->getMessage()]
            );
            $this->logger->critical($message);

            throw new LocalizedException($message);
        }
    }
}
