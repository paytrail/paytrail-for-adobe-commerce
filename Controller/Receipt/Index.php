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
use Paytrail\PaymentService\Model\FinnishReferenceNumber;

class Index implements ActionInterface
{
    public const ORDER_SUCCESS_STATUSES = ["processing", "pending_paytrail", "pending", "complete"];
    public const ORDER_CANCEL_STATUSES = ["canceled"];

    /**
     * Index constructor.
     * @param Context $context
     * @param Session $session
     * @param ResponseValidator $responseValidator
     * @param ReceiptDataProvider $receiptDataProvider
     * @param ProcessPayment $processPayment
     * @param Config $gatewayConfig
     * @param Data $paytrailHelper
     * @param OrderFactory $orderFactory
     */
    public function __construct(
        private FinnishReferenceNumber $referenceNumber,
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
     * @throws \Exception
     * @see \Paytrail\PaymentService\Controller\Callback\Index
     * execute method
     */
    public function execute()
    {
        $successStatuses = ["processing", "pending_paytrail", "pending", "complete"];
        $cancelStatuses  = ["canceled"];
        $reference = $this->request->getParam('checkout-reference');

        $order = $this->referenceNumber->getOrderByReference($reference);
        $status = $order->getStatus();

        $failMessages = $this->processPayment->process($this->request->getParams(), $this->session);

        if ($status == 'pending_payment' || in_array($status, $cancelStatuses)) {
            // order status could be changed by callback, if not,
            // status change needs to be forced by processing the payment
            $failMessages = $this->processPayment->process($this->request->getParams(), $this->session);
        }

        if ($status == 'pending_payment') { // status could be changed by callback, if not, it needs to be forced
            $order  = $this->referenceNumber->getOrderByReference($reference); // refreshing order
            $status = $order->getStatus(); // getting current status
        }

        /** @var \Magento\Framework\Controller\Result\Redirect $result */
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
