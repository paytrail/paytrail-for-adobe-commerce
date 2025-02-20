<?php

namespace Paytrail\PaymentService\Controller\Redirect;

use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\UrlInterface;
use Magento\Framework\Validation\ValidationException;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\CommandManagerPoolInterface;
use Magento\Sales\Model\Order;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Model\PaymentMethod\OrderPaymentMethodData;
use Paytrail\PaymentService\Model\Receipt\ProcessService;
use Paytrail\PaymentService\Model\Validation\PreventAdminActions;
use Psr\Log\LoggerInterface;

class PayAndAddCard implements ActionInterface
{
    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var null
     */
    private $errorMsg = null;

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
     * @param OrderPaymentMethodData $paymentMethodData
     */
    public function __construct(
        private Context                     $context,
        private Session                     $checkoutSession,
        private JsonFactory                 $jsonFactory,
        private LoggerInterface             $logger,
        private CustomerSession             $customerSession,
        private PreventAdminActions         $preventAdminActions,
        private CommandManagerPoolInterface $commandManagerPool,
        private ProcessService              $processService,
        private OrderPaymentMethodData $paymentMethodData
    ) {
        $this->urlBuilder = $context->getUrl();
    }

    /**
     * Execute method.
     *
     * @return ResponseInterface|Json|ResultInterface
     * @throws ValidationException
     */
    public function execute()
    {
        if ($this->preventAdminActions->isAdminAsCustomer()) {
            throw new ValidationException(__('Admin user is not authorized for this operation'));
        }

        $resultJson = $this->jsonFactory->create();

        $order = $this->checkoutSession->getLastRealOrder();

        // set selected payment method to order's payment additional_data
        $this->paymentMethodData->setSelectedCardTokenData($order, 'pay_and_add_card');

        try {
            if ($this->customerSession->getCustomerId() && $this->context->getRequest()->getParam('is_ajax')) {
                $responseData = $this->getResponseData($order);
                $redirectUrl  = $responseData->getRedirectUrl();

                return $resultJson->setData(
                    [
                        'success'  => true,
                        'data'     => 'redirect',
                        'redirect' => $redirectUrl
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
     * Get response from Paytrail API.
     *
     * @param Order $order
     *
     * @return mixed
     * @throws CheckoutException
     * @throws NotFoundException
     * @throws CommandException
     */
    private function getResponseData($order): mixed
    {
        $commandExecutor = $this->commandManagerPool->get('paytrail');
        $response        = $commandExecutor->executeByCode(
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
