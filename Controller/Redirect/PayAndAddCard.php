<?php

namespace Paytrail\PaymentService\Controller\Redirect;

use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Validation\ValidationException;
use Magento\Payment\Gateway\Command\CommandManagerPoolInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\OrderFactory;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Model\Receipt\ProcessService;
use Paytrail\PaymentService\Model\Validation\PreventAdminActions;
use Psr\Log\LoggerInterface;

class PayAndAddCard extends \Magento\Framework\App\Action\Action
{
    protected $urlBuilder;

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var JsonFactory
     */
    protected $jsonFactory;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Config
     */
    protected $gatewayConfig;

    /**
     * @var $errorMsg
     */
    protected $errorMsg = null;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var PreventAdminActions
     */
    protected $preventAdminActions;

    /**
     * @var OrderFactory
     */
    private $orderFactory;
    /**
     * @var CartManagementInterface
     */
    private $cartManagement;
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;
    /**
     * @var QuoteManagement
     */
    private $quoteManagement;

    /**
     * PayAndAddCard constructor.
     *
     * @param Context $context
     * @param Session $checkoutSession
     * @param JsonFactory $jsonFactory
     * @param LoggerInterface $logger
     * @param Config $gatewayConfig
     * @param CustomerSession $customerSession
     * @param PreventAdminActions $preventAdminActions
     * @param OrderRepositoryInterface $orderRepository
     * @param CartManagementInterface $cartManagement
     * @param QuoteManagement $quoteManagement
     * @param CommandManagerPoolInterface $commandManagerPool
     * @param ProcessService $processService
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        JsonFactory $jsonFactory,
        LoggerInterface $logger,
        Config $gatewayConfig,
        CustomerSession $customerSession,
        PreventAdminActions $preventAdminActions,
        OrderRepositoryInterface $orderRepository,
        CartManagementInterface $cartManagement,
        QuoteManagement $quoteManagement,
        private CommandManagerPoolInterface $commandManagerPool,
        private ProcessService $processService
    ) {
        $this->urlBuilder = $context->getUrl();
        $this->checkoutSession = $checkoutSession;
        $this->jsonFactory = $jsonFactory;
        $this->logger = $logger;
        $this->gatewayConfig = $gatewayConfig;
        $this->customerSession = $customerSession;
        parent::__construct($context);
        $this->preventAdminActions = $preventAdminActions;
        $this->cartManagement = $cartManagement;
        $this->orderRepository = $orderRepository;
        $this->quoteManagement = $quoteManagement;
    }

    /**
     * Execute.
     *
     * @return \Magento\Framework\App\ResponseInterface|Json|\Magento\Framework\Controller\ResultInterface
     * @throws ValidationException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute()
    {
        if ($this->preventAdminActions->isAdminAsCustomer()) {
            throw new ValidationException(__('Admin user is not authorized for this operation'));
        }

        /** @var Json $resultJson */
        $resultJson = $this->jsonFactory->create();

//        $orderId = $this->cartManagement->placeOrder($this->checkoutSession->getQuoteId());
//        $order = $this->orderRepository->get($orderId);
        $order = $this->quoteManagement->submit($this->checkoutSession->getQuote());

        try {
            if ($this->customerSession->getCustomerId() && $this->getRequest()->getParam('is_ajax')) {
                $responseData = $this->getResponseData($order);
                $redirect_url = $responseData->getRedirectUrl();

                return $resultJson->setData(
                    [
                        'success' => true,
                        'data' => 'redirect',
                        'redirect' => $redirect_url
                    ]
                );
            }
        } catch (\Exception $e) {
            // Error will be handled below
            $this->logger->error($e->getMessage());
        }

        return $resultJson->setData(
            [
                'success' => false,
                'message' => $this->errorMsg
            ]
        );
    }

    /**
     * @param Magento\Sales\Model\Order $order
     * @return mixed
     * @throws CheckoutException
     */
    protected function getResponseData($order)
    {
        $commandExecutor = $this->commandManagerPool->get('paytrail');
        $response = $commandExecutor->executeByCode(
            'pay_and_add_card',
            null,
            [
                'order' => $order
            ]
        );

        if ($response['error']) {
            $this->errorMsg = ($response['error']);
            $this->processService->processError($response['error']);
        }

        return $response["data"];
    }
}
