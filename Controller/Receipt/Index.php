<?php

namespace Paytrail\PaymentService\Controller\Receipt;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Model\Order;
use Paytrail\PaymentService\Model\FinnishReferenceNumber;
use Paytrail\PaymentService\Model\Receipt\ProcessPayment;

class Index implements ActionInterface
{
    public const ORDER_SUCCESS_STATUSES = ["processing", "pending_paytrail", "pending", "complete"];
    public const ORDER_CANCEL_STATUSES  = ["canceled"];

    /**
     * Index constructor.
     *
     * @param FinnishReferenceNumber $referenceNumber
     * @param Session $session
     * @param ProcessPayment $processPayment
     * @param RequestInterface $request
     * @param ResultFactory $resultFactory
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        private FinnishReferenceNumber $referenceNumber,
        private Session                $session,
        private ProcessPayment         $processPayment,
        private RequestInterface       $request,
        private ResultFactory          $resultFactory,
        private ManagerInterface       $messageManager
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
        $reference = $this->request->getParam('checkout-reference');

        $order  = $this->referenceNumber->getOrderByReference($reference);
        $status = $order->getStatus();

        $failMessages = $this->processPayment->process($this->request->getParams(), $this->session);

        if ($status == 'pending_payment') { // status could be changed by callback, if not, it needs to be forced
            $order  = $this->referenceNumber->getOrderByReference($reference); // refreshing order
            $status = $order->getStatus(); // getting current status
        }

        $result = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
        if (in_array($status, self::ORDER_SUCCESS_STATUSES)) {
            return $result->setPath($this->getSuccessUrl($order));
        } elseif (in_array($status, self::ORDER_CANCEL_STATUSES)) {
            foreach ($failMessages as $failMessage) {
                $this->messageManager->addErrorMessage($failMessage);
            }

            return $result->setPath($this->getCartUrl($order));
        }

        $this->messageManager->addErrorMessage(
            __('Order processing has been aborted. Please contact customer service.')
        );

        return $result->setPath($this->getCartUrl($order));
    }

    /**
     * Method to use for plugins if pwa-graphql installed
     *
     * @param Order $order
     *
     * @return string
     */
    public function getSuccessUrl(Order $order): string
    {
        return 'checkout/onepage/success';
    }


    /**
     * @param Order $order
     *
     * @return string
     */
    public function getCartUrl(Order $order): string
    {
        return 'checkout/cart';
    }
}
