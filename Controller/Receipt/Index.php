<?php

namespace Paytrail\PaymentService\Controller\Receipt;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Model\Order;
use Paytrail\PaymentService\Helper\ProcessPayment;
use Paytrail\PaymentService\Model\FinnishReferenceNumber;

/**
 * Class Index
 *
 * Receipt Controller
 */
class Index implements ActionInterface
{
    /**
     * @var Session
     */
    protected Session $session;

    /**
     * @var FinnishReferenceNumber
     */
    protected FinnishReferenceNumber $referenceNumber;

    /**
     * @var ProcessPayment
     */
    private ProcessPayment $processPayment;

    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @var ResultFactory
     */
    private ResultFactory $resultFactory;

    /**
     * @var ManagerInterface
     */
    private ManagerInterface $messageManager;

    /**
     * @param Session $session
     * @param ProcessPayment $processPayment
     * @param RequestInterface $request
     * @param ResultFactory $resultFactory
     * @param ManagerInterface $messageManager
     * @param \Paytrail\PaymentService\Model\FinnishReferenceNumber $referenceNumber
     */
    public function __construct(
        Session                $session,
        ProcessPayment         $processPayment,
        RequestInterface       $request,
        ResultFactory          $resultFactory,
        ManagerInterface       $messageManager,
        FinnishReferenceNumber $referenceNumber
    ) {
        $this->session         = $session;
        $this->processPayment  = $processPayment;
        $this->request         = $request;
        $this->resultFactory   = $resultFactory;
        $this->messageManager  = $messageManager;
        $this->referenceNumber = $referenceNumber;
    }

    /**
     * Order status is manipulated by another callback:
     *
     * @throws \Exception
     * @see \Paytrail\PaymentService\Controller\Callback\Index
     */
    public function execute()
    {
        $successStatuses = ["processing", "pending_paytrail", "pending", "complete"];
        $cancelStatuses  = ["canceled"];
        $reference       = $this->request->getParam('checkout-reference');

        sleep(2); //giving callback time to get processed

        $order = $this->referenceNumber->getOrderByReference($reference);

        $status = $order->getStatus();

        /** @var array $failMessages */
        $failMessages = [];

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
        if (in_array($status, $successStatuses)) {
            return $result->setPath($this->getSuccessUrl($order));
        } elseif (in_array($status, $cancelStatuses)) {
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
