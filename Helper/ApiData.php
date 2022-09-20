<?php

namespace Paytrail\PaymentService\Helper;

use GuzzleHttp\Exception\RequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Gateway\Config\Config as GatewayConfig;
use Paytrail\PaymentService\Helper\Data as CheckoutHelper;
use Paytrail\PaymentService\Logger\PaytrailLogger;
use Paytrail\PaymentService\Model\Adapter\Adapter;
use Paytrail\SDK\Model\CallbackUrl;
use Paytrail\SDK\Request\EmailRefundRequest;
use Paytrail\SDK\Request\PaymentRequest;
use Paytrail\SDK\Request\RefundRequest;
use Paytrail\SDK\Request\AddCardFormRequest;
use Paytrail\SDK\Request\CitPaymentRequest;
use Paytrail\SDK\Request\GetTokenRequest;
use Paytrail\SDK\Request\PaymentStatusRequest;
use Paytrail\PaymentService\Model\Token\RequestData;
use Psr\Log\LoggerInterface;

/**
 * Class ApiData
 */
class ApiData
{
    /**
     * @var CheckoutHelper
     */
    private $helper;

    /**
     * @var PaytrailLogger
     */
    private $log;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var Adapter
     */
    private $paytrailAdapter;

    /**
     * @var PaymentRequest
     */
    private $paymentRequest;

    /**
     * @var RefundRequest
     */
    private $refundRequest;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var EmailRefundRequest
     */
    private $emailRefundRequest;

    /**
     * @var RequestData
     */
    private $requestData;

    /**
     * @var GatewayConfig
     */
    private $gatewayConfig;

    /**
     * @var AddCardFormRequest
     */
    private $addCardFormRequest;

    /**
     * @var GetTokenRequest
     */
    private $getTokenRequest;

    /**
     * @var CitPaymentRequest
     */
    private $citPaymentRequest;

    /**
     * @var PaymentStatusRequest
     */
    private $paymentStatusRequest;

    public function __construct(
        LoggerInterface $log,
        UrlInterface $urlBuilder,
        RequestInterface $request,
        Json $json,
        CheckoutHelper $helper,
        GatewayConfig $gatewayConfig,
        StoreManagerInterface $storeManager,
        Adapter $paytrailAdapter,
        PaymentRequest $paymentRequest,
        RefundRequest $refundRequest,
        EmailRefundRequest $emailRefundRequest,
        AddCardFormRequest $addCardFormRequest,
        GetTokenRequest $getTokenRequest,
        CitPaymentRequest $citPaymentRequest,
        PaymentStatusRequest $paymentStatusRequest,
        RequestData $requestData
    ) {
        $this->log = $log;
        $this->urlBuilder = $urlBuilder;
        $this->request = $request;
        $this->json = $json;
        $this->helper = $helper;
        $this->gatewayConfig = $gatewayConfig;
        $this->paytrailAdapter = $paytrailAdapter;
        $this->paymentRequest = $paymentRequest;
        $this->refundRequest = $refundRequest;
        $this->storeManager = $storeManager;
        $this->emailRefundRequest = $emailRefundRequest;
        $this->addCardFormRequest = $addCardFormRequest;
        $this->getTokenRequest = $getTokenRequest;
        $this->citPaymentRequest = $citPaymentRequest;
        $this->paymentStatusRequest = $paymentStatusRequest;
        $this->requestData = $requestData;
    }

    /**
     * Process Api request
     *
     * @param string $requestType
     * @param Order|null $order
     * @param int|float $amount
     * @param mixed $transactionId
     * @param mixed $methodId
     * @param mixed $tokenizationId
     * @return mixed
     */
    public function processApiRequest(
        $requestType,
        $order = null,
        $amount = null,
        $transactionId = null,
        $methodId = null,
        $tokenizationId = null
    ) {
        $response["data"] = null;
        $response["error"] = null;

        try {
            $paytrailClient = $this->paytrailAdapter->initPaytrailMerchantClient();

            $this->log->debugLog(
                'request',
                \sprintf(
                    'Creating %s request to Paytrail API %s',
                    $requestType,
                    isset($order) ? 'With order id: ' . $order->getId() : ''
                )
            );

            // Handle payment requests
            if ($requestType === 'payment') {
                $paytrailPayment = $this->paymentRequest;
                $this->setPaymentRequestData($paytrailPayment, $order);

                $response["data"] = $paytrailClient->createPayment($paytrailPayment);

                $loggedData = $this->json->serialize([
                    'transactionId' => $response["data"]->getTransactionId(),
                    'href' => $response["data"]->getHref()
                ]);

                $this->log->debugLog(
                    'response',
                    sprintf(
                        'Successful response for order id: %s with data: %s',
                        $order->getId(),
                        $loggedData
                    )
                );
            } elseif ($requestType === "token_payment") {
                // token payment using Customer Initialized Transaction
                $paytrailPayment = $this->citPaymentRequest;
                $paytrailPayment = $this->requestData->setTokenPaymentRequestData($paytrailPayment, $order, $methodId, $tokenizationId);
                $response["data"] = $paytrailClient->createCitPaymentCharge($paytrailPayment);
                $loggedData = $this->json->serialize([
                    'transactionId' => $response["data"]->getTransactionId(),
                    '3ds' => $response["data"]->getThreeDSecureUrl()
                ]);
                $this->log->debugLog(
                    'response',
                    sprintf(
                        'Successful response for order Id %s with data: %s',
                        $order->getId(),
                        $loggedData
                    )
                );
            } elseif ($requestType === 'get_payment_data') {
                $paymentStatus = $this->paymentStatusRequest;
                $paymentStatus->setTransactionId($transactionId);
                $response["data"] = $paytrailClient->getPaymentStatus($paymentStatus);

                $this->log->debugLog(
                    'response',
                    sprintf(
                        'Successful response for transaction %s. Data: %s',
                        $transactionId,
                        $this->json->serialize($response["data"])
                    )
                );
            } elseif ($requestType === 'refund') {
                // Handle refund requests
                $paytrailRefund = $this->refundRequest;
                $this->setRefundRequestData($paytrailRefund, $amount);

                $response["data"] = $paytrailClient->refund($paytrailRefund, $transactionId);

                $this->log->debugLog(
                    'response',
                    sprintf(
                        'Successful response for refund. Transaction Id: %s',
                        $response["data"]->getTransactionId()
                    )
                );
            } elseif ($requestType === 'email_refund') {
                $paytrailEmailRefund = $this->emailRefundRequest;
                $this->setEmailRefundRequestData($paytrailEmailRefund, $amount, $order);

                $response["data"] = $paytrailClient->emailRefund($paytrailEmailRefund, $transactionId);

                $this->log->debugLog(
                    'response',
                    sprintf(
                        'Successful response for email refund. Transaction Id: %s',
                        $response["data"]->getTransactionId()
                    )
                );
            } elseif ($requestType === 'payment_providers') {
                $response["data"] = $paytrailClient->getGroupedPaymentProviders(
                    $amount,
                    $this->helper->getStoreLocaleForPaymentProvider()
                );
                $this->log->debugLog(
                    'response',
                    'Successful response for payment providers.'
                );
            } elseif ($requestType === 'add_card') {
                $addCardFormRequest = $this->addCardFormRequest;
                $this->setAddCardFormRequestData($addCardFormRequest);
                $response['data'] = $paytrailClient->createAddCardFormRequest($addCardFormRequest);
                $this->log->debugLog(
                    'response',
                    'Successful response for adding card form.'
                );
                // Handle token request
            } elseif ($requestType === 'token_request') {
                $getTokenRequest = $this->getTokenRequest;
                $getTokenRequest->setCheckoutTokenizationId($tokenizationId);
                $response['data'] = $paytrailClient->createGetTokenRequest($getTokenRequest);
                $this->log->debugLog(
                    'response',
                    'Successful response for getting token request.'
                );
            }
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $this->log->error(\sprintf(
                    'Connection error to Paytrail Payment Service API: %s Error Code: %s',
                    $e->getMessage(),
                    $e->getCode()
                ));
                $response["error"] = $e->getMessage();
                return $response;
            }
        } catch (\Exception $e) {
            $this->log->error(
                \sprintf(
                    'A problem occurred during Paytrail Api connection: %s',
                    $e->getMessage()
                ),
                $e->getTrace()
            );
            $response["error"] = $e->getMessage();
            return $response;
        }

        return $response;
    }

    /**
     * @param PaymentRequest $paytrailPayment
     * @param Order $order
     * @return mixed
     * @throws \Exception
     */
    protected function setPaymentRequestData($paytrailPayment, $order)
    {
        $billingAddress = $order->getBillingAddress() ?? $order->getShippingAddress();
        $shippingAddress = $order->getShippingAddress();

        $paytrailPayment->setStamp(hash(
            $this->gatewayConfig->getCheckoutAlgorithm(),
            time() . $order->getIncrementId()
        ));

        $paytrailPayment->setReference($this->helper->getReference($order));
        $paytrailPayment->setCurrency($order->getOrderCurrencyCode());
        $paytrailPayment->setAmount(round($order->getGrandTotal() * 100));
        $paytrailPayment->setCustomer($this->requestData->createCustomer($billingAddress));
        $paytrailPayment->setInvoicingAddress($this->requestData->createAddress($order, $billingAddress));

        if (!is_null($shippingAddress)) {
            $paytrailPayment->setDeliveryAddress($this->requestData->createAddress($order, $shippingAddress));
        }

        $paytrailPayment->setLanguage($this->helper->getStoreLocaleForPaymentProvider());
        $paytrailPayment->setItems($this->requestData->getOrderItemLines($order));
        $paytrailPayment->setRedirectUrls($this->createRedirectUrl());
        $paytrailPayment->setCallbackUrls($this->createCallbackUrl());

        // Log payment data
        $this->log->debugLog('request', $paytrailPayment);

        return $paytrailPayment;
    }

    /**
     * @param RefundRequest $paytrailRefund
     * @param $amount
     * @throws CheckoutException
     */
    protected function setRefundRequestData($paytrailRefund, $amount)
    {
        if ($amount <= 0) {
            $this->helper->processError('Refund amount must be above 0');
        }

        $paytrailRefund->setAmount(round($amount * 100));

        $callback = $this->createRefundCallback();
        $paytrailRefund->setCallbackUrls($callback);
    }

    /**
     * @param EmailRefundRequest $paytrailEmailRefund
     * @param $amount
     * @param $order
     */
    protected function setEmailRefundRequestData($paytrailEmailRefund, $amount, $order)
    {
        $paytrailEmailRefund->setEmail($order->getBillingAddress()->getEmail());

        $paytrailEmailRefund->setAmount(round($amount * 100));

        $callback = $this->createRefundCallback();
        $paytrailEmailRefund->setCallbackUrls($callback);
    }

    /**
     * @param AddCardFormRequest $addCardFormRequest
     */
    protected function setAddCardFormRequestData($addCardFormRequest)
    {
        $datetime = new \DateTime();
        $saveCardUrl = $this->gatewayConfig->getSaveCardUrl();

        $addCardFormRequest->setCheckoutAccount($this->gatewayConfig->getMerchantId());
        $addCardFormRequest->setCheckoutAlgorithm($this->gatewayConfig->getCheckoutAlgorithm());
        $addCardFormRequest->setCheckoutRedirectSuccessUrl($this->getCallbackUrl($saveCardUrl));
        $addCardFormRequest->setCheckoutRedirectCancelUrl($this->getCallbackUrl($saveCardUrl));
        $addCardFormRequest->setLanguage($this->helper->getStoreLocaleForPaymentProvider());
        $addCardFormRequest->setCheckoutMethod('POST');
        $addCardFormRequest->setCheckoutTimestamp($datetime->format('Y-m-d\TH:i:s.u\Z'));
        $addCardFormRequest->setCheckoutNonce(uniqid(true));
    }

    /**
     * @return CallbackUrl
     */
    protected function createRefundCallback()
    {
        $callback = new CallbackUrl();

        try {
            $storeUrl = $this->storeManager
                ->getStore()
                ->getBaseUrl(UrlInterface::URL_TYPE_WEB, true);
        } catch (NoSuchEntityException $e) {
            $storeUrl = $this->urlBuilder->getBaseUrl();
        }

        $callback->setSuccess($storeUrl);
        $callback->setCancel($storeUrl);

        return $callback;
    }

    /**
     * @return CallbackUrl
     */
    protected function createRedirectUrl()
    {
        $callback = new CallbackUrl();

        $callback->setSuccess($this->getCallbackUrl('receipt'));
        $callback->setCancel($this->getCallbackUrl('receipt'));

        return $callback;
    }

    /**
     * @return CallbackUrl
     */
    protected function createCallbackUrl()
    {
        $callback = new CallbackUrl();

        $callback->setSuccess($this->getCallbackUrl('callback'));
        $callback->setCancel($this->getCallbackUrl('callback'));

        return $callback;
    }

    /**
     * @param $param
     * @return string
     */
    protected function getCallbackUrl($param)
    {
        $routeParams = [
            '_secure' => $this->request->isSecure(),
        ];

        if ($this->request->getParam('custom_redirect_url')) {
            $routeParams['custom_redirect_url'] = $this->request->getParam('custom_redirect_url');
        }

        return $this->urlBuilder->getUrl('paytrail/' . $param, $routeParams);
    }

    /**
     * @param $params
     * @param $signature
     * @return bool
     */
    public function validateHmac($params, $signature)
    {
        try {
            $this->log->debugLog(
                'request',
                \sprintf(
                    'Validating Hmac for transaction: %s',
                    $params["checkout-transaction-id"]
                )
            );
            $paytrailClient = $this->paytrailAdapter->initPaytrailMerchantClient();

            $paytrailClient->validateHmac($params, '', $signature);
        } catch (\Exception $e) {
            $this->log->error(sprintf(
                'Paytrail PaymentService error: Hmac validation failed for transaction %s',
                $params["checkout-transaction-id"]
            ));
            return false;
        }
        $this->log->debugLog(
            'response',
            sprintf(
                'Hmac validation successful for transaction: %s',
                $params["checkout-transaction-id"]
            )
        );
        return true;
    }
}
