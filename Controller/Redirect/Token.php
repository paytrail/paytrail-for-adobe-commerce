<?php

namespace Paytrail\PaymentService\Controller\Redirect;

use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Gateway\Validator\ResponseValidator;
use Paytrail\PaymentService\Helper\ApiData;
use Paytrail\PaymentService\Helper\Data;
use Paytrail\PaymentService\Model\ReceiptDataProvider;
use Paytrail\PaymentService\Model\Recurring\TotalConfigProvider;
use Paytrail\PaymentService\Model\Subscription\SubscriptionCreate;

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

    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @var OrderFactory
     */
    private OrderFactory $orderFactory;

    /**
     * @var Session
     */
    private Session $checkoutSession;

    /**
     * @var CustomerSession
     */
    private CustomerSession $customerSession;

    /**
     * @var ApiData
     */
    private ApiData $apiData;

    /**
     * @var JsonFactory
     */
    private JsonFactory $jsonFactory;

    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * @var OrderManagementInterface
     */
    private OrderManagementInterface $orderManagementInterface;

    /**
     * @var TotalConfigProvider
     */
    private TotalConfigProvider $totalConfigProvider;

    /**
     * @param Session $session
     * @param ResponseValidator $responseValidator
     * @param QuoteRepository $quoteRepository
     * @param ReceiptDataProvider $receiptDataProvider
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
     * @param SubscriptionCreate $subscriptionCreate
     */
    public function __construct(
        Session                  $session,
        ResponseValidator        $responseValidator,
        QuoteRepository          $quoteRepository,
        ReceiptDataProvider      $receiptDataProvider,
        Config                   $gatewayConfig,
        Data                     $opHelper,
        RequestInterface         $request,
        OrderFactory             $orderFactory,
        Session                  $checkoutSession,
        CustomerSession          $customerSession,
        ApiData                  $apiData,
        JsonFactory              $jsonFactory,
        OrderRepositoryInterface $orderRepository,
        OrderManagementInterface $orderManagementInterface,
        SubscriptionCreate       $subscriptionCreate,
        TotalConfigProvider $totalConfigProvider
    ) {
        $this->session = $session;
        $this->responseValidator = $responseValidator;
        $this->receiptDataProvider = $receiptDataProvider;
        $this->quoteRepository = $quoteRepository;
        $this->gatewayConfig = $gatewayConfig;
        $this->opHelper = $opHelper;
        $this->request = $request;
        $this->orderFactory = $orderFactory;
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->apiData = $apiData;
        $this->jsonFactory = $jsonFactory;
        $this->orderRepository = $orderRepository;
        $this->orderManagementInterface = $orderManagementInterface;
        $this->subscriptionCreate = $subscriptionCreate;
        $this->totalConfigProvider = $totalConfigProvider;
    }

    /**
     * Execute function
     *
     * @return ResponseInterface|Json|ResultInterface
     * @throws CheckoutException
     * @throws LocalizedException
     * @throws CouldNotSaveException
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
        
        $resultJson = $this->jsonFactory->create();
        if ($order->getStatus() === Order::STATE_PROCESSING) {
            $this->errorMsg = __('Payment already processed');
            return $resultJson->setData(
                [
                    'success' => false,
                    'message' => $this->errorMsg
                ]
            );
        }

        $customer = $this->customerSession->getCustomer();
        try {
            $responseData = $this->getTokenResponseData($order, $selectedTokenId, $customer);
            if ($this->totalConfigProvider->isRecurringPaymentEnabled()) {
                if ($this->subscriptionCreate->getSubscriptionSchedule($order) && $responseData->getTransactionId()) {
                    $orderSchedule = $this->subscriptionCreate->getSubscriptionSchedule($order);
                    $this->subscriptionCreate->createSubscription(
                        $orderSchedule,
                        $selectedTokenId,
                        $customer->getId(),
                        $order->getId()
                    );
                }
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

            return $resultJson->setData(
                [
                    'success' => false,
                    'message' => $this->errorMsg
                ]
            );
        }

        $redirectUrl = $responseData->getThreeDSecureUrl();
        $resultJson = $this->jsonFactory->create();

        if ($redirectUrl) {
            return $resultJson->setData(
                [
                    'success' => true,
                    'data' => 'redirect',
                    'redirect' => $redirectUrl
                ]
            );
        }

        /* fetch payment response using transaction id */
        $response = $this->apiData->processApiRequest(
            'get_payment_data',
            null,
            null,
            $responseData->getTransactionId()
        );

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
                'redirect' => $redirectUrl
            ]
        );
    }

    /**
     * GetTokenResponseData function
     *
     * @param Order $order
     * @param string $tokenId
     * @param Customer $customer
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

        if (isset($errorMsg)) {
            $this->errorMsg = ($errorMsg);
            $this->opHelper->processError($errorMsg);
        }

        return $response["data"];
    }
}
