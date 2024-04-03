<?php

namespace Paytrail\PaymentService\Controller\Tokenization;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Validation\ValidationException;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\CommandManagerPoolInterface;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Model\Receipt\ProcessService;
use Paytrail\PaymentService\Model\Validation\PreventAdminActions;
use Psr\Log\LoggerInterface;

class AddCard implements \Magento\Framework\App\ActionInterface
{

    private $errorMsg = null;

    /**
     * AddCard constructor.
     *
     * @param Context $context
     * @param JsonFactory $jsonFactory
     * @param LoggerInterface $logger
     * @param CustomerSession $customerSession
     * @param PreventAdminActions $preventAdminActions
     * @param ProcessService $processService
     * @param CommandManagerPoolInterface $commandManagerPool
     */
    public function __construct(
        private Context                     $context,
        private JsonFactory                 $jsonFactory,
        private LoggerInterface             $logger,
        private CustomerSession             $customerSession,
        private PreventAdminActions         $preventAdminActions,
        private ProcessService              $processService,
        private CommandManagerPoolInterface $commandManagerPool
    ) {
    }

    /**
     * Execute
     *
     * @return mixed
     * @throws ValidationException
     */
    public function execute()
    {
        if ($this->preventAdminActions->isAdminAsCustomer()) {
            throw new ValidationException(__('Admin user is not authorized for this operation'));
        }

        $resultJson = $this->jsonFactory->create();

        try {
            if ($this->customerSession->getCustomerId() && $this->context->getRequest()->getParam('is_ajax')) {
                $responseData = $this->getResponseData();
                $redirect_url = $responseData->getHeader('Location')[0];

                return $resultJson->setData(
                    [
                        'success'  => true,
                        'data'     => 'redirect',
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
     * Get add_card response data.
     *
     * @return mixed
     * @throws CheckoutException
     * @throws NotFoundException
     * @throws CommandException
     */
    private function getResponseData()
    {
        $commandExecutor = $this->commandManagerPool->get('paytrail');
        $response        = $commandExecutor->executeByCode(
            'add_card',
            null,
            [
                'custom_redirect_url' => $this->context->getRequest()->getParam('custom_redirect_url')
            ]
        );

        $errorMsg = $response['error'];

        if (isset($errorMsg)) {
            $this->errorMsg = ($errorMsg);
            $this->processService->processError($errorMsg);
        }

        return $response["data"];
    }
}
