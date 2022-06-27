<?php
declare(strict_types=1);

namespace Paytrail\PaymentService\Model\Token;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\DB\Transaction;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Paytrail\PaymentService\Helper\Data;
use Paytrail\PaymentService\Model\Adapter\Adapter;
use Paytrail\SDK\Request\MitPaymentRequest;

class Payment
{
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var Adapter
     */
    private $adapter;

    /**
     * @var Data
     */
    private $helper;

    /**
     * @var RequestData
     */
    private $requestData;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var InvoiceService
     */
    private $invoiceService;

    /**
     * @var Transaction
     */
    private $transaction;

    /**
     * @var Order
     */
    private $currentOrder;

    /**
     * @var BuilderInterface
     */
    private $transactionBuilder;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param Adapter $adapter
     * @param Data $helper
     * @param RequestData $requestData
     * @param CustomerRepositoryInterface $customerRepository
     * @param InvoiceService $invoiceService
     * @param Transaction $transaction
     * @param Order $currentOrder
     * @param BuilderInterface $transactionBuilder
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        Adapter $adapter,
        Data $helper,
        RequestData $requestData,
        CustomerRepositoryInterface $customerRepository,
        InvoiceService $invoiceService,
        Transaction $transaction,
        Order $currentOrder,
        BuilderInterface $transactionBuilder
    ) {
        $this->orderRepository = $orderRepository;
        $this->adapter = $adapter;
        $this->helper = $helper;
        $this->requestData = $requestData;
        $this->customerRepository = $customerRepository;
        $this->invoiceService = $invoiceService;
        $this->transaction = $transaction;
        $this->currentOrder = $currentOrder;
        $this->transactionBuilder = $transactionBuilder;
    }

    /**
     * @param $orderId
     * @param $cardToken
     * @return bool
     * @throws LocalizedException
     */
    public function makeMitPayment($orderId, $cardToken)
    {
        try {
            $order = $this->orderRepository->get($orderId);
            $client = $this->adapter->initPaytrailMerchantClient();
            $customer = $this->customerRepository->getById((int)$order->getCustomerId());

            $mitPayment = $this->getMitPaymentRequest();
            $mitPayment = $this->requestData->setTokenPaymentRequestData($mitPayment, $order, $cardToken, $customer);

            /** @var \Paytrail\SDK\Response\MitPaymentResponse $mitResponse */
            $mitResponse = $client->createMitPaymentCharge($mitPayment);
            if (!$mitResponse->getTransactionId()) {
                $this->helper->logCheckoutData(
                    'response',
                    'error',
                    'A problem occurred: '
                    . 'Payment transaction id missing in request response'
                );

                return false;
            }
        } catch (\Exception $e) {
            $this->helper->logCheckoutData(
                'response',
                'error',
                'A problem occurred: '
                . $e->getMessage()
            );
            return false;
        }
        $this->createInvoice($order, $mitResponse);
        $this->createTransaction($order, $mitResponse);
        $this->updateOrder($order, $mitResponse);

        return true;
    }

    /**
     * @param $order
     * @param $mitResponse
     * @throws LocalizedException
     */
    private function createInvoice($order,$mitResponse)
    {
        if ($order->canInvoice()) {
            try {
                $invoice = $this->invoiceService->prepareInvoice($order);
                $invoice->register();
                $invoice->setTransactionId($mitResponse->getTransactionId());
                $invoice->save();
                $transactionSave = $this->transaction->addObject(
                    $invoice
                )->addObject(
                    $invoice->getOrder()
                );
                $transactionSave->save();
            } catch (\Exception $e) {
                $this->helper->logCheckoutData(
                    'response',
                    'error',
                    'A problem with creating invoice after payment '
                    . $e->getMessage()
                );
            }
        }
    }

    /**
     * @param $order
     * @param $mitResponse
     * @return int|void
     */
    private function createTransaction($order, $mitResponse)
    {
        try {
            $payment = $order->getPayment();
            $payment->setLastTransId($mitResponse->getTransactionId());
            $payment->setTransactionId($mitResponse->getTransactionId());
            $payment->setAdditionalInformation(
                [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $mitResponse]
            );

            $trans = $this->transactionBuilder;
            $transaction = $trans->setPayment($payment)
                ->setOrder($order)
                ->setTransactionId($mitResponse->getTransactionId())
                ->setAdditionalInformation(
                    [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $mitResponse]
                )
                ->setFailSafe(true)
                //build method creates the transaction and returns the object
                ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE);

            $payment->setParentTransactionId(null);
            $payment->save();
            $order->save();

            return  $transaction->save()->getTransactionId();
        } catch (\Exception $e) {
            $this->helper->logCheckoutData(
                'response',
                'error',
                'A problem occurred: while creating transaction'
                . $e->getMessage()
            );
        }
    }

    /**
     * @return MitPaymentRequest
     */
    private function getMitPaymentRequest()
    {
        return new MitPaymentRequest();
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param \Paytrail\SDK\Response\MitPaymentResponse $mitResponse
     * @return void
     */
    private function updateOrder(
        \Magento\Sales\Api\Data\OrderInterface $order,
        \Paytrail\SDK\Response\MitPaymentResponse $mitResponse
    ): void {
        $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
        $order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
        $order->addCommentToStatusHistory(__('Payment has been completed'));
        $order->addCommentToStatusHistory(__('Transaction ID: ') . $mitResponse->getTransactionId());

        $this->orderRepository->save($order);
    }
}
