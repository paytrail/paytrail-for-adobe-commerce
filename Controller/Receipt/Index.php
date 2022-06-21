<?php

namespace Paytrail\PaymentService\Controller\Receipt;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Api\Data\OrderInterface;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Gateway\Validator\ResponseValidator;
use Paytrail\PaymentService\Helper\Data;
use Paytrail\PaymentService\Helper\ProcessPayment;
use Paytrail\PaymentService\Model\ReceiptDataProvider;
use Magento\Framework\App\Action\HttpGetActionInterface;

/**
 * Class Index
 */
class Index implements HttpGetActionInterface
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
     * @var ReceiptDataProvider
     */
    protected $receiptDataProvider;

    /**
     * @var QuoteRepository
     */
    protected $quoteRepository;

    /**
     * @var OrderInterface
     */
    private $orderInterface;

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
    private RequestInterface $request;
    private Context $context;
    private RedirectFactory $redirectFactory;
    private ManagerInterface $messageManager;

    /**
     * Index constructor.
     * @param Context $context
     * @param Session $session
     * @param ResponseValidator $responseValidator
     * @param QuoteRepository $quoteRepository
     * @param ReceiptDataProvider $receiptDataProvider
     * @param OrderInterface $orderInterface
     * @param ProcessPayment $processPayment
     * @param Config $gatewayConfig
     * @param Data $paytrailHelper
     * @param RequestInterface $request
     * @param RedirectFactory $redirectFactory
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        Context $context,
        Session $session,
        ResponseValidator $responseValidator,
        QuoteRepository $quoteRepository,
        ReceiptDataProvider $receiptDataProvider,
        OrderInterface $orderInterface,
        ProcessPayment $processPayment,
        Config $gatewayConfig,
        Data $paytrailHelper,
        RequestInterface $request,
        RedirectFactory $redirectFactory,
        ManagerInterface $messageManager
    ) {
        $this->session = $session;
        $this->responseValidator = $responseValidator;
        $this->receiptDataProvider = $receiptDataProvider;
        $this->quoteRepository = $quoteRepository;
        $this->orderInterface = $orderInterface;
        $this->processPayment = $processPayment;
        $this->gatewayConfig = $gatewayConfig;
        $this->paytrailHelper = $paytrailHelper;
        $this->request = $request;
        $this->context = $context;
        $this->redirectFactory = $redirectFactory;
        $this->messageManager = $messageManager;
    }

    /**
     * execute method
     */
    public function execute() // there is also other call which changes order status
    {

        /** @var array $successStatuses */
        $successStatuses = ["processing", "pending_paytrail", "pending", "complete"];

        /** @var array $cancelStatuses */
        $cancelStatuses = ["canceled"];

        /** @var string $reference */
        $reference = $this->request->getParam('checkout-reference');

        /** @var string $orderNo */
        $orderNo = $this->gatewayConfig->getGenerateReferenceForOrder()
            ? $this->paytrailHelper->getIdFromOrderReferenceNumber($reference)
            : $reference;

        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->orderInterface->loadByIncrementId($orderNo);

        sleep(2); //giving callback time to get processed

        /** @var string $status */
        $status = $order->getStatus();

        /** @var array $failMessages */
        $failMessages = [];

        if ($status == 'pending_payment' || in_array($status, $cancelStatuses)) {
            // order status could be changed by callback, if not, status change needs to be forced by processing the payment
            $failMessages = $this->processPayment->process($this->request->getParams(), $this->session);
        }

        if ($status == 'pending_payment') { // status could be changed by callback, if not, it needs to be forced
            $order = $this->orderInterface->loadByIncrementId($orderNo); // refreshing order
            $status = $order->getStatus(); // getting current status
        }

        $redirect = $this->redirectFactory->create();

        if (in_array($status, $successStatuses)) {
            $redirect->setPath('checkout/onepage/success');
            return $redirect;
        } elseif (in_array($status, $cancelStatuses)) {
            /** @var string $failMessage */
            foreach ($failMessages as $failMessage) {
                $this->messageManager->addErrorMessage($failMessage);
            }
            $redirect->setPath('checkout/cart');
            return $redirect;
        }

        $this->messageManager->addErrorMessage(__(
            'Order processing has been aborted. Please contact customer service.'
        ));
        $redirect->setPath('checkout/cart');
        return $redirect;
    }
}
