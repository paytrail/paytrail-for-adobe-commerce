<?php

namespace Paytrail\PaymentService\Controller\Redirect;

use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\UrlInterface;
use Magento\Framework\Validation\ValidationException;
use Magento\Payment\Gateway\Command\CommandManagerPoolInterface;
use Magento\Sales\Model\Order;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Model\Receipt\ProcessService;
use Paytrail\PaymentService\Model\Validation\PreventAdminActions;
use Psr\Log\LoggerInterface;

class PayAndAddCard extends \Magento\Framework\App\Action\Action
{
    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var $errorMsg
     */
    protected $errorMsg = null;

    /**
     * PayAndAddCard constructor.
     *
     * @param Context $context
     * @param Session $checkoutSession
     * @param JsonFactory $jsonFactory
     * @param LoggerInterface $logger
     * @param CustomerSession $customerSession
     * @param PreventAdminActions $preventAdminActions
     * @param CommandManagerPoolInterface $commandManagerPool
     * @param ProcessService $processService
     */
    public function __construct(
        Context $context,
        private Session $checkoutSession,
        private JsonFactory $jsonFactory,
        private LoggerInterface $logger,
        private CustomerSession $customerSession,
        private PreventAdminActions $preventAdminActions,
        private CommandManagerPoolInterface $commandManagerPool,
        private ProcessService $processService
    ) {
        $this->urlBuilder = $context->getUrl();
        parent::__construct($context);
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

        $order = $this->checkoutSession->getLastRealOrder();

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
     * Get response from Paytail API.
     *
     * @param Order $order
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
