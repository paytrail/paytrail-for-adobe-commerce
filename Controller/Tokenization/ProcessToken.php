<?php

namespace Paytrail\PaymentService\Controller\Tokenization;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Payment\Gateway\Command\CommandManagerPoolInterface;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Model\Receipt\ProcessService;
use Psr\Log\LoggerInterface;

class ProcessToken extends Action
{
    /**
     * @var $errorMsg
     */
    protected $errorMsg = null;

    /**
     * ProcessToken constructor
     *
     * @param Context $context
     * @param JsonFactory $jsonFactory
     * @param LoggerInterface $logger
     * @param ProcessService $processService
     * @param CommandManagerPoolInterface $commandManagerPool
     */
    public function __construct(
        Context $context,
        private JsonFactory $jsonFactory,
        private LoggerInterface $logger,
        private ProcessService $processService,
        private CommandManagerPoolInterface $commandManagerPool
    ) {
        parent::__construct($context);
    }

    /**
     * Execute
     *
     * @return mixed
     */
    public function execute()
    {
        /** @var Json $resultJson */
        $resultJson = $this->jsonFactory->create();

        try {
            if ($this->getRequest()->getParam('is_ajax')) {

                $responseData = $this->getResponseData();
                $redirect_url = $responseData->getHeader('Location')[0];

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
     * Get add_card response data.
     *
     * @return mixed
     * @throws CheckoutException
     */
    protected function getResponseData()
    {
        $commandExecutor = $this->commandManagerPool->get('paytrail');
        $response = $commandExecutor->executeByCode('add_card');

        $errorMsg = $response['error'];

        if (isset($errorMsg)) {
            $this->errorMsg = ($errorMsg);
            $this->processService->processError($errorMsg);
        }

        return $response["data"];
    }
}
