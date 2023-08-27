<?php

namespace Paytrail\PaymentService\Controller\Callback;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Sales\Model\OrderFactory;
use Paytrail\PaymentService\Controller\Receipt\Index as Receipt;
use Paytrail\PaymentService\Gateway\Config\Config;
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
     */
    public function __construct(
        private Session $session,
        private ProcessPayment $processPayment,
        private RequestInterface $request,
        private ResultFactory $resultFactory,
        private Config $gatewayConfig,
        private OrderFactory $orderFactory
    ) {
    }

    /**
     * Execute function
     *
     * @return ResultInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(): ResultInterface
    {
        $reference = $this->request->getParam('checkout-reference');
        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        /** @var string $orderNo */
        $orderNo = $this->gatewayConfig->getGenerateReferenceForOrder()
            ? $this->gatewayConfig->getIdFromOrderReferenceNumber($reference)
            : $reference;

        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->orderFactory->create()->loadByIncrementId($orderNo);
        $status = $order->getStatus();

        if ($status == 'pending_payment' || in_array($status, Receipt::ORDER_CANCEL_STATUSES)) {
            // order status could be changed by receipt
            // if not, status change needs to be forced by processing the payment
            $response['error'] = $this->processPayment->process($this->request->getParams(), $this->session);
        }

        return $response;
    }
}
