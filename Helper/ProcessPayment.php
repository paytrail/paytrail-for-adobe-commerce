<?php

namespace Paytrail\PaymentService\Helper;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\CacheInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteRepository;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Gateway\Validator\ResponseValidator;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Model\ReceiptDataProvider;
use Paytrail\PaymentService\Exceptions\TransactionSuccessException;

/**
 * Class ProcessPayment
 */
class ProcessPayment
{
    const PAYMENT_PROCESSING_CACHE_PREFIX = "paytrail-processing-payment-";

    /**
     * @var ResponseValidator
     */
    private $responseValidator;

    /**
     * @var ReceiptDataProvider
     */
    private $receiptDataProvider;

    /**
     * @var QuoteRepository
     */
    private $cartRepository;

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var Config
     */
    private $gatewayConfig;

    /**
     * @var Data
     */
    private $paytrailHelper;

    /**
     * ProcessPayment constructor.
     * @param ResponseValidator $responseValidator
     * @param ReceiptDataProvider $receiptDataProvider
     * @param QuoteRepository $cartRepository
     * @param CacheInterface $cache
     * @param Config $gatewayConfig
     * @param Data $paytrailHelper
     */
    public function __construct(
        ResponseValidator       $responseValidator,
        ReceiptDataProvider     $receiptDataProvider,
        CartRepositoryInterface $cartRepository,
        CacheInterface          $cache,
        Config                  $gatewayConfig,
        Data                    $paytrailHelper
    ) {
        $this->responseValidator = $responseValidator;
        $this->receiptDataProvider = $receiptDataProvider;
        $this->cartRepository = $cartRepository;
        $this->cache = $cache;
        $this->gatewayConfig = $gatewayConfig;
        $this->paytrailHelper = $paytrailHelper;
    }

    /**
     * @param array $params
     * @param Session $session
     * @return array
     */
    public function process($params, $session)
    {
        /** @var array $errors */
        $errors = [];

        /** @var \Magento\Payment\Gateway\Validator\Result $validationResponse */
        $validationResponse = $this->responseValidator->validate($params);

        if (!$validationResponse->isValid()) { // if response params are not valid, redirect back to the cart

            /** @var string $failMessage */
            foreach ($validationResponse->getFailsDescription() as $failMessage) {
                array_push($errors, $failMessage);
            }

            $session->restoreQuote(); // should it be restored?

            return $errors;
        }

        /** @var string $reference */
        $reference = $params['checkout-reference'];

        /** @var string $orderNo */
        $orderNo = $this->gatewayConfig->getGenerateReferenceForOrder()
            ? $this->paytrailHelper->getIdFromOrderReferenceNumber($reference)
            : $reference;

        /** @var int $count */
        $count = 0;
        while ($this->isPaymentLocked($orderNo) && $count < 5) {
            $count++;
        }

        $this->lockProcessingPayment($orderNo);

        /** @var array $ret */
        $ret = $this->processPayment($params, $session, $orderNo);

        $this->unlockProcessingPayment($orderNo);

        return array_merge($ret, $errors);
    }

    /**
     * @param array $params
     * @param Session $session
     * @param $orderNo
     * @return array
     */
    protected function processPayment($params, $session, $orderNo)
    {
        /** @var array $errors */
        $errors = [];

        /** @var bool $isValid */
        $isValid = true;

        /** @var null|string $failMessage */
        $failMessage = null;

        if (empty($orderNo)) {
            $session->restoreQuote();

            return $errors;
        }

        try {
            /*
            there are 2 calls called from Paytrail Payment Service.
            One call is when a customer is redirected back to the magento store.
            There is also the second, parallel, call from Paytrail Payment Service to make sure the payment is confirmed (if for any reason customer was not redirected back to the store).
            Sometimes, the calls are called with too small time difference between them that Magento cannot handle them. The second call must be ignored or slowed down.
            */
            $this->receiptDataProvider->execute($params);
        } catch (CheckoutException $exception) {
            $isValid = false;
            $failMessage = $exception->getMessage();
            array_push($errors, $failMessage);
        } catch (TransactionSuccessException $successException) {
            $isValid = true;
        }

        if ($isValid == false) {
            $session->restoreQuote();
        } else {
            /** @var \Magento\Quote\Model\Quote $quote */
            $quote = $session->getQuote();
            $quote->setIsActive(false);
            $this->cartRepository->save($quote);
        }

        return $errors;
    }

    /**
     * @param int $orderId
     */
    protected function lockProcessingPayment($orderId)
    {
        /** @var string $identifier */
        $identifier = self::PAYMENT_PROCESSING_CACHE_PREFIX . $orderId;

        $this->cache->save("locked", $identifier);
    }

    /**
     * @param int $orderId
     */
    protected function unlockProcessingPayment($orderId)
    {
        /** @var string $identifier */
        $identifier = self::PAYMENT_PROCESSING_CACHE_PREFIX . $orderId;

        $this->cache->remove($identifier);
    }

    /**
     * @param int $orderId
     * @return bool
     */
    protected function isPaymentLocked($orderId)
    {
        /** @var string $identifier */
        $identifier = self::PAYMENT_PROCESSING_CACHE_PREFIX . $orderId;

        return $this->cache->load($identifier) ? true : false;
    }
}
