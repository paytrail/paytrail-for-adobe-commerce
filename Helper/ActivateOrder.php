<?php
namespace Paytrail\PaymentService\Helper;

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\DB\TransactionFactory;

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
     * @var Order
     */
    protected $orderResourceModel;
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
     * ActivateOrder constructor.
     * @param OrderRepositoryInterface $orderRepository
     * @param TransactionRepositoryInterface $transactionRepository
     * @param InvoiceService $invoiceService
     * @param TransactionFactory $transactionFactory
     * @param Order $orderResourceModel
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        TransactionRepositoryInterface $transactionRepository,
        InvoiceService $invoiceService,
        TransactionFactory $transactionFactory,
        Order $orderResourceModel
    ) {
        $this->orderResourceModel = $orderResourceModel;
        $this->orderRepository = $orderRepository;
        $this->transactionRepository = $transactionRepository;
        $this->invoiceService = $invoiceService;
        $this->transactionFactory = $transactionFactory;
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

        $this->orderResourceModel->save($order);
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
     */
    protected function processInvoice($order)
    {
        $transactionId = $this->getCaptureTransaction($order);

        if ($order->canInvoice()) {
            try {
                $invoice = $this->invoiceService->prepareInvoice($order);
                $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
                $invoice->setTransactionId($transactionId);
                $invoice->register();
                $transactionSave = $this->transactionFactory->create();
                $transactionSave->addObject(
                    $invoice
                )->addObject(
                    $order
                )->save();
            } catch (LocalizedException $exception) {
                $invoiceFailException = $exception->getMessage();
            }

            if (isset($invoiceFailException)) {
                $this->processError($invoiceFailException);
            }
        }
    }
}
