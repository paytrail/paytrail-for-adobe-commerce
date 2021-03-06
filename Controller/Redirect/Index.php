<?php

namespace Paytrail\PaymentService\Controller\Redirect;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Helper\ApiData;
use Paytrail\PaymentService\Helper\Data as paytrailHelper;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\SDK\Model\Provider;
use Paytrail\SDK\Response\PaymentResponse;
use Psr\Log\LoggerInterface;

/**
 * Class Index
 */
class Index extends \Magento\Framework\App\Action\Action
{
    protected $urlBuilder;

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var JsonFactory
     */
    protected $jsonFactory;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepositoryInterface;

    /**
     * @var OrderManagementInterface
     */
    protected $orderManagementInterface;

    /**
     * @var PageFactory
     */
    protected $pageFactory;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ApiData
     */
    protected $apiData;

    /**
     * @var paytrailHelper
     */
    protected $paytrailHelper;

    /**
     * @var Config
     */
    protected $gatewayConfig;

    /**
     * @var $errorMsg
     */
    protected $errorMsg = null;

    /**
     * Index constructor.
     *
     * @param Context $context
     * @param Session $checkoutSession
     * @param OrderFactory $orderFactory
     * @param JsonFactory $jsonFactory
     * @param OrderRepositoryInterface $orderRepositoryInterface
     * @param OrderManagementInterface $orderManagementInterface
     * @param PageFactory  $pageFactory
     * @param LoggerInterface $logger
     * @param ApiData $apiData
     * @param paytrailHelper $paytrailHelper
     * @param Config $gatewayConfig
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        OrderFactory $orderFactory,
        JsonFactory $jsonFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepositoryInterface,
        OrderManagementInterface $orderManagementInterface,
        PageFactory $pageFactory,
        LoggerInterface $logger,
        ApiData $apiData,
        paytrailHelper $paytrailHelper,
        Config $gatewayConfig
    ) {
        $this->urlBuilder = $context->getUrl();
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->jsonFactory = $jsonFactory;
        $this->orderRepositoryInterface = $orderRepositoryInterface;
        $this->orderManagementInterface = $orderManagementInterface;
        $this->pageFactory = $pageFactory;
        $this->logger = $logger;
        $this->apiData = $apiData;
        $this->paytrailHelper = $paytrailHelper;
        $this->gatewayConfig = $gatewayConfig;
        parent::__construct($context);
    }

    /**
     * @return mixed
     */
    public function execute()
    {
        /** @var Json $resultJson */
        $resultJson = $this->jsonFactory->create();

        $order = null;
        try {
            if ($this->getRequest()->getParam('is_ajax')) {
                $selectedPaymentMethodRaw = $this->getRequest()->getParam(
                    'preselected_payment_method_id'
                );
                $selectedPaymentMethodId = strpos($selectedPaymentMethodRaw, '-') !== false
                    ? explode('-', $selectedPaymentMethodRaw)[0]
                    : $selectedPaymentMethodRaw;

                if (empty($selectedPaymentMethodId)) {
                    $this->errorMsg = __('No payment method selected');
                    throw new LocalizedException(__('No payment method selected'));
                }

                /** @var Order $order */
                $order = $this->orderFactory->create();
                $order = $order->loadByIncrementId(
                    $this->checkoutSession->getLastRealOrderId()
                );

                $responseData = $this->getResponseData($order);
                $formData = $this->getFormFields(
                    $responseData,
                    $selectedPaymentMethodId
                );
                $formAction = $this->getFormAction(
                    $responseData,
                    $selectedPaymentMethodId
                );

                if ($this->gatewayConfig->getSkipBankSelection()) {
                    $redirect_url = $responseData->getHref();

                    return $resultJson->setData(
                        [
                            'success' => true,
                            'data' => 'redirect',
                            'redirect' => $redirect_url
                        ]
                    );
                }

                $block = $this->pageFactory
                    ->create()
                    ->getLayout()
                    ->createBlock(\Paytrail\PaymentService\Block\Redirect\Paytrail::class)
                    ->setUrl($formAction)
                    ->setParams($formData);

                return $resultJson->setData(
                    [
                        'success' => true,
                        'data' => $block->toHtml(),
                    ]
                );
            }
        } catch (\Exception $e) {
            // Error will be handled below
            $this->logger->error($e->getMessage());
        }

        if ($order) {
            $this->orderManagementInterface->cancel($order->getId());
            $order->addCommentToStatusHistory(
                __('Order canceled. Failed to redirect to Paytrail Payment Service.')
            );
            $this->orderRepositoryInterface->save($order);
        }

        $this->checkoutSession->restoreQuote();
        $resultJson = $this->jsonFactory->create();

        return $resultJson->setData(
            [
                'success' => false,
                'message' => $this->errorMsg
            ]
        );
    }

    /**
     * @param PaymentResponse $responseData
     * @param $paymentMethodId
     * @return array
     */
    protected function getFormFields($responseData, $paymentMethodId = null)
    {
        $formFields = [];

        /** @var Provider $provider */
        foreach ($responseData->getProviders() as $provider) {
            if ($provider->getId() == $paymentMethodId) {
                foreach ($provider->getParameters() as $parameter) {
                    $formFields[$parameter->name] = $parameter->value;
                }
            }
        }

        return $formFields;
    }

    /**
     * @param PaymentResponse $responseData
     * @param $paymentMethodId
     * @return string
     */
    protected function getFormAction($responseData, $paymentMethodId = null)
    {
        $returnUrl = '';

        /** @var Provider $provider */
        foreach ($responseData->getProviders() as $provider) {
            if ($provider->getId() == $paymentMethodId) {
                $returnUrl = $provider->getUrl();
            }
        }

        return $returnUrl;
    }

    /**
     * @param Order $order
     * @param string $methodId
     * @return PaymentResponse
     * @throws CheckoutException
     */
    protected function getResponseData($order)
    {
        $response = $this->apiData->processApiRequest('payment', $order);

        $errorMsg = $response['error'];

        if (isset($errorMsg)) {
            $this->errorMsg = ($errorMsg);
            $this->paytrailHelper->processError($errorMsg);
        }

        return $response["data"];
    }
}
