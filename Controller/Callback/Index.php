<?php

namespace Paytrail\PaymentService\Controller\Callback;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Sales\Model\OrderFactory;
use Paytrail\PaymentService\Controller\Receipt\Index as Receipt;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Helper\Data;
use Paytrail\PaymentService\Helper\ProcessPayment;

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
     * @param Data $paytrailHelper
     * @param OrderFactory $orderFactory
     */
    public function __construct(
        private Session $session,
        private ProcessPayment $processPayment,
        private RequestInterface $request,
        private ResultFactory $resultFactory,
        private Config $gatewayConfig,
        private Data $paytrailHelper,
        private OrderFactory $orderFactory,
        private \Paytrail\PaymentService\Model\Token\SaveCreditCard $saveCreditCard
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

        if ($this->request->getParam('checkout-card-token')) {
            $this->saveCreditCard->saveCard($this->request->getParam('checkout-card-token'));
        }

        /** @var string $orderNo */
        $orderNo = $this->gatewayConfig->getGenerateReferenceForOrder()
            ? $this->paytrailHelper->getIdFromOrderReferenceNumber($reference)
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
