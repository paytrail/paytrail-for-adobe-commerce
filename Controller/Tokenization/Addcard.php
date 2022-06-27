<?php

namespace Paytrail\PaymentService\Controller\Tokenization;

use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Helper\ApiData;
use Paytrail\PaymentService\Helper\Data as opHelper;
use Paytrail\PaymentService\Gateway\Config\Config;
use Psr\Log\LoggerInterface;

/**
 * Class Addcard
 */
class AddCard extends \Magento\Framework\App\Action\Action
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
     * @var ApiData
     */
    protected $apiData;

    /**
     * @var opHelper
     */
    protected $opHelper;

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
     * AddCard constructor.
     * @param Context $context
     * @param Session $checkoutSession
     * @param JsonFactory $jsonFactory
     * @param LoggerInterface $logger
     * @param ApiData $apiData
     * @param opHelper $opHelper
     * @param Config $gatewayConfig
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        JsonFactory $jsonFactory,
        LoggerInterface $logger,
        ApiData $apiData,
        opHelper $opHelper,
        Config $gatewayConfig,
        CustomerSession $customerSession
    ) {
        $this->urlBuilder = $context->getUrl();
        $this->checkoutSession = $checkoutSession;
        $this->jsonFactory = $jsonFactory;
        $this->logger = $logger;
        $this->apiData = $apiData;
        $this->opHelper = $opHelper;
        $this->gatewayConfig = $gatewayConfig;
        $this->customerSession = $customerSession;
        parent::__construct($context);
    }

    /**
     * @return mixed
     */
    public function execute()
    {
        /** @var Json $resultJson */
        $resultJson = $this->jsonFactory->create();

        try {
            if ($this->customerSession->getCustomerId() && $this->getRequest()->getParam('is_ajax')) {

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
     * @return mixed
     * @throws CheckoutException
     */
    protected function getResponseData()
    {
        $response = $this->apiData->processApiRequest('add_card');

        $errorMsg = $response['error'];

        if (isset($errorMsg)){
            $this->errorMsg = ($errorMsg);
            $this->opHelper->processError($errorMsg);
        }

        return $response["data"];
    }
}
