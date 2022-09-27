<?php

namespace Paytrail\PaymentService\Controller\Receipt;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Model\OrderFactory;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Gateway\Validator\ResponseValidator;
use Paytrail\PaymentService\Helper\Data;
use Paytrail\PaymentService\Helper\ProcessPayment;

/**
 * Class Index
 */
class Index implements ActionInterface
{
    /**
     * @var Session
     */
    protected $session;

    /**
     * @var ResponseValidator
     */
    protected $responseValidator;
    /**
     * @var ProcessPayment
     */
    private $processPayment;
    /**
     * @var Config
     */
    private $gatewayConfig;
    /**
     * @var Data
     */
    private $paytrailHelper;
    /**
     * @var OrderFactory
     */
    private $orderFactory;
    /**
     * @var RequestInterface
     */
    private $request;
    /**
     * @var ResultFactory
     */
    private $resultFactory;

    /**
     * @var ManagerInterface
     */
    private $messageManager;


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
        Session $session,
        ResponseValidator $responseValidator,
        ProcessPayment $processPayment,
        Config $gatewayConfig,
        Data $paytrailHelper,
        OrderFactory $orderFactory,
        RequestInterface $request,
        ResultFactory $resultFactory,
        ManagerInterface $messageManager
    ) {
        $this->session = $session;
        $this->responseValidator = $responseValidator;
        $this->processPayment = $processPayment;
        $this->gatewayConfig = $gatewayConfig;
        $this->paytrailHelper = $paytrailHelper;
        $this->orderFactory = $orderFactory;
        $this->request = $request;
        $this->resultFactory = $resultFactory;
        $this->messageManager = $messageManager;
    }

    /**
     * Order status is manipulated by another callback:
     * @see \Paytrail\PaymentService\Controller\Callback\Index
     * execute method
     */
    public function execute()
    {
        $successStatuses = ["processing", "pending_paytrail", "pending", "complete"];
        $cancelStatuses = ["canceled"];
        $reference = $this->request->getParam('checkout-reference');

        /** @var string $orderNo */
        $orderNo = $this->gatewayConfig->getGenerateReferenceForOrder()
            ? $this->paytrailHelper->getIdFromOrderReferenceNumber($reference)
            : $reference;

        sleep(2); //giving callback time to get processed

        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->orderFactory->create()->loadByIncrementId($orderNo);
        $status = $order->getStatus();

        /** @var array $failMessages */
        $failMessages = [];

        if ($status == 'pending_payment' || in_array($status, $cancelStatuses)) {
            // order status could be changed by callback, if not, status change needs to be forced by processing the payment
            $failMessages = $this->processPayment->process($this->request->getParams(), $this->session);
        }

        if ($status == 'pending_payment') { // status could be changed by callback, if not, it needs to be forced
            $order = $this->orderFactory->create()->loadByIncrementId($orderNo); // refreshing order
            $status = $order->getStatus(); // getting current status
        }

        $result = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
        if (in_array($status, $successStatuses)) {
            return $result->setUrl('checkout/onepage/success');
        } elseif (in_array($status, $cancelStatuses)) {
            foreach ($failMessages as $failMessage) {
                $this->messageManager->addErrorMessage($failMessage);
            }

            return $result->setUrl('checkout/cart');
        }

        $this->messageManager->addErrorMessage(__('Order processing has been aborted. Please contact customer service.'));
        return $result->setUrl('checkout/cart');
    }
}
