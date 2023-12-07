<?php

namespace Paytrail\PaymentService\Controller\Callback;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\OrderFactory;
use Paytrail\PaymentService\Controller\Receipt\Index as Receipt;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Logger\PaytrailLogger;
use Paytrail\PaymentService\Model\Receipt\ProcessPayment;

class Index implements \Magento\Framework\App\ActionInterface
{
    /**
     * Index constructor.
     *
     * @param Session $session
     * @param ProcessPayment $processPayment
     * @param RequestInterface $request
     * @param ResultFactory $resultFactory
     * @param Config $gatewayConfig
     * @param OrderFactory $orderFactory
     * @param PaytrailLogger $logger
     */
    public function __construct(
        private Session          $session,
        private ProcessPayment   $processPayment,
        private RequestInterface $request,
        private ResultFactory    $resultFactory,
        private Config           $gatewayConfig,
        private OrderFactory     $orderFactory,
        private PaytrailLogger   $logger
    ) {
    }

    /**
     * Execute function
     *
     * @return ResultInterface
     * @throws LocalizedException
     */
    public function execute(): ResultInterface
    {
        $reference     = $this->request->getParam('checkout-reference');
        $response      = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $responseError = [];

        /** @var string $orderNo */
        $orderNo = $this->gatewayConfig->getGenerateReferenceForOrder()
            ? $this->gatewayConfig->getIdFromOrderReferenceNumber($reference)
            : $reference;

        /** @var \Magento\Sales\Model\Order $order */
        $order  = $this->orderFactory->create()->loadByIncrementId($orderNo);
        $status = $order->getStatus();

        $this->logger->debugLog(
            'request',
            'callback received' . PHP_EOL .
            'order status: ' . $status . PHP_EOL .
            'orderNo: ' . $orderNo . PHP_EOL .
            'params: ' . json_encode($this->request->getParams(), JSON_PRETTY_PRINT)
        );

        if ($status == 'pending_payment' || in_array($status, Receipt::ORDER_CANCEL_STATUSES)) {
            // order status could be changed by receipt
            // if not, status change needs to be forced by processing the payment

            $responseError = $this->processPayment->process($this->request->getParams(), $this->session);
        }

        $responseData = ['error' => $responseError];

        $this->logger->debugLog(
            'request',
            'callback response' . PHP_EOL .
            'order status: ' . $status . PHP_EOL .
            'orderNo: ' . $orderNo . PHP_EOL .
            'response: ' . json_encode($responseData, JSON_PRETTY_PRINT)
        );

        return $response->setData($responseData);
    }
}
