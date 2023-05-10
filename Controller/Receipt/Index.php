<?php

namespace Paytrail\PaymentService\Controller\Receipt;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Model\OrderFactory;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Helper\Data;
use Paytrail\PaymentService\Helper\ProcessPayment;

class Index implements ActionInterface
{
    public const ORDER_SUCCESS_STATUSES = ["processing", "pending_paytrail", "pending", "complete"];
    public const ORDER_CANCEL_STATUSES = ["canceled"];

    /**
     * Index constructor.
     *
     * @param Session $session
     * @param ProcessPayment $processPayment
     * @param Config $gatewayConfig
     * @param Data $paytrailHelper
     * @param OrderFactory $orderFactory
     * @param RequestInterface $request
     * @param ResultFactory $resultFactory
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        private Session $session,
        private ProcessPayment $processPayment,
        private Config $gatewayConfig,
        private Data $paytrailHelper,
        private OrderFactory $orderFactory,
        private RequestInterface $request,
        private ResultFactory $resultFactory,
        private ManagerInterface $messageManager
    ) {
    }

    /**
     * Order status is manipulated by another callback:
     *
     * @see \Paytrail\PaymentService\Controller\Callback\Index
     * execute method
     */
    public function execute()
    {
        $reference = $this->request->getParam('checkout-reference');

        /** @var string $orderNo */
        $orderNo = $this->gatewayConfig->getGenerateReferenceForOrder()
            ? $this->paytrailHelper->getIdFromOrderReferenceNumber($reference)
            : $reference;
        
        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->orderFactory->create()->loadByIncrementId($orderNo);
        $status = $order->getStatus();

        $failMessages = $this->processPayment->process($this->request->getParams(), $this->session);

        if ($status == 'pending_payment') { // status could be changed by callback, if not, it needs to be forced
            $order = $this->orderFactory->create()->loadByIncrementId($orderNo); // refreshing order
            $status = $order->getStatus(); // getting current status
        }

        $result = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
        if (in_array($status, self::ORDER_SUCCESS_STATUSES)) {
            return $result->setPath('checkout/onepage/success');
        } elseif (in_array($status, self::ORDER_CANCEL_STATUSES)) {
            foreach ($failMessages as $failMessage) {
                $this->messageManager->addErrorMessage($failMessage);
            }

            return $result->setPath('checkout/cart');
        }

        $this->messageManager->addErrorMessage(
            __('Order processing has been aborted. Please contact customer service.')
        );
        return $result->setPath('checkout/cart');
    }
}
