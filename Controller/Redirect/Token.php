<?php

namespace Paytrail\PaymentService\Controller\Redirect;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\OrderFactory;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Gateway\Validator\ResponseValidator;
use Paytrail\PaymentService\Helper\ApiData;
use Paytrail\PaymentService\Helper\Data;
use Paytrail\PaymentService\Helper\ProcessPayment;
use Paytrail\PaymentService\Model\ReceiptDataProvider;
use Paytrail\PaymentService\Model\Subscription\SubscriptionCreate;
use Paytrail\SDK\Model\Provider;
use Paytrail\SDK\Response\PaymentResponse;

/**
 * Class Index
 */
class Token implements HttpPostActionInterface
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
     * @var SubscriptionCreate
     */
    private $subscriptionCreate;

    /**
     * @var Data
     */
    private $opHelper;
    private RequestInterface $request;
    private Context $context;
    private OrderFactory $orderFactory;
    private Session $checkoutSession;
    private CustomerSession $customerSession;
    private ApiData $apiData;
    private JsonFactory $jsonFactory;
    private OrderRepositoryInterface $orderRepository;
    private OrderManagementInterface $orderManagementInterface;
    private OrderPaymentInterface $orderPaymentInterface;
    private ForwardFactory $forwardFactory;
    private RedirectFactory $redirectFactory;

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
     * @param Data $opHelper
     * @param RequestInterface $request
     * @param OrderFactory $orderFactory
     * @param Session $checkoutSession
     * @param CustomerSession $customerSession
     * @param ApiData $apiData
     * @param JsonFactory $jsonFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderManagementInterface $orderManagementInterface
     * @param OrderPaymentInterface $orderPaymentInterface
     * @param ForwardFactory $forwardFactory
     * @param RedirectFactory $redirectFactory
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
        Data $opHelper,
        RequestInterface $request,
        OrderFactory $orderFactory,
        Session $checkoutSession,
        CustomerSession $customerSession,
        ApiData $apiData,
        JsonFactory $jsonFactory,
        OrderRepositoryInterface $orderRepository,
        OrderManagementInterface $orderManagementInterface,
        OrderPaymentInterface $orderPaymentInterface,
        ForwardFactory $forwardFactory,
        RedirectFactory $redirectFactory,
        SubscriptionCreate $subscriptionCreate
    ) {
        $this->session = $session;
        $this->responseValidator = $responseValidator;
        $this->receiptDataProvider = $receiptDataProvider;
        $this->quoteRepository = $quoteRepository;
        $this->orderInterface = $orderInterface;
        $this->processPayment = $processPayment;
        $this->gatewayConfig = $gatewayConfig;
        $this->opHelper = $opHelper;
        $this->request = $request;
        $this->context = $context;
        $this->orderFactory = $orderFactory;
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->apiData = $apiData;
        $this->jsonFactory = $jsonFactory;
        $this->orderRepository = $orderRepository;
        $this->orderManagementInterface = $orderManagementInterface;
        $this->orderPaymentInterface = $orderPaymentInterface;
        $this->forwardFactory = $forwardFactory;
        $this->redirectFactory = $redirectFactory;
        $this->subscriptionCreate = $subscriptionCreate;
    }

    /**
     * execute method
     */
    public function execute() // there is also other call which changes order status
    {
        $selectedTokenRaw = $this->request->getParam('selected_token');
        $selectedTokenId = preg_replace('/[^0-9a-f]{2,}$/', '', $selectedTokenRaw);

        if (empty($selectedTokenId)) {
            $this->errorMsg = __('No payment token selected');
            throw new LocalizedException(__('No payment token selected'));
        }

        $order = $this->orderFactory->create();
        $order = $order->loadByIncrementId(
            $this->checkoutSession->getLastRealOrderId()
        );

        $customer = $this->customerSession->getCustomer();
        try {
            $responseData = $this->getTokenResponseData($order, $selectedTokenId, $customer);
            if ($this->subscriptionCreate->getSubscriptionSchedule($order) && $responseData->getTransactionId()) {
                $orderSchedule = $this->subscriptionCreate->getSubscriptionSchedule($order);
                $this->subscriptionCreate->createSubscription(
                    $orderSchedule,
                    $selectedTokenId,
                    $customer->getId(),
                    $order->getId()
                );
            }
        } catch (CheckoutException $exception) {
            $this->errorMsg = __('Error processing token payment');
              if ($order) {
                $this->orderManagementInterface->cancel($order->getId());
                $order->addCommentToStatusHistory(
                    __('Order canceled. Failed to process token payment.')
                );
                $this->orderRepository->save($order);
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

        $redirect_url = $responseData->getThreeDSecureUrl();
        $resultJson = $this->jsonFactory->create();

        if(!empty($redirect_url))
        {
            return $resultJson->setData(
                [
                    'success' => true,
                    'data' => 'redirect',
                    'redirect' => $redirect_url
                ]
            );
        }

        /* fetch payment response using transaction id */
        $response = $this->apiData->processApiRequest('get_payment_data', null, null, $responseData->getTransactionId());

        $receiptData = [
            'checkout-account' => $this->gatewayConfig->getMerchantId(),
            'checkout-algorithm' => 'sha256',
            'checkout-amount' => $response['data']->getAmount(),
            'checkout-stamp' => $response['data']->getStamp(),
            'checkout-reference' => $response['data']->getReference(),
            'checkout-transaction-id' => $response['data']->getTransactionId(),
            'checkout-status' => $response['data']->getStatus(),
            'checkout-provider' => $response['data']->getProvider(),
        ];

        $this->receiptDataProvider->execute($receiptData);

        return $resultJson->setData(
            [
                'success' => true,
                'data' => 'redirect',
                'reference' => $response['data']->getReference(),
                'redirect' => $redirect_url
            ]
        );
    }

    /**
     * @param $order
     * @param $tokenId
     * @return mixed
     * @throws CheckoutException
     */
    protected function getTokenResponseData($order, $tokenId, $customer)
    {
        $response = $this->apiData->processApiRequest(
            'token_payment',
            $order,
            null,
            null,
            $tokenId,
            $customer
        );

        $errorMsg = $response['error'];

        if (isset($errorMsg)){
            $this->errorMsg = ($errorMsg);
            $this->opHelper->processError($errorMsg);
        }

        return $response["data"];
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
}
