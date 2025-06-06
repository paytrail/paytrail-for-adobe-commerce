<?php

namespace Paytrail\PaymentService\Controller\Callback;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Model\Order;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Model\PaymentTokenFactory;
use Magento\Vault\Model\PaymentTokenManagement;
use Monolog\Logger;
use Paytrail\PaymentService\Controller\Receipt\Index as Receipt;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Logger\PaytrailLogger;
use Paytrail\PaymentService\Model\FinnishReferenceNumber;
use Paytrail\PaymentService\Model\Receipt\ProcessPayment;

class PayAndAddCardCallback implements ActionInterface
{
    /**
     * @var string[]
     */
    private $cardTypes = [
        'Visa'       => 'VI',
        'MasterCard' => 'MC',
        'Discover'   => 'DI',
        'Amex'       => 'AE',
        'Maestro'    => 'SM',
        'Solo'       => 'SO'
    ];

    /**
     * PayAndAddCardCallback constructor.
     *
     * @param Session $session
     * @param ProcessPayment $processPayment
     * @param RequestInterface $request
     * @param ResultFactory $resultFactory
     * @param Config $gatewayConfig
     * @param FinnishReferenceNumber $referenceNumber
     * @param PaymentTokenFactory $paymentTokenFactory
     * @param SerializerInterface $jsonSerializer
     * @param PaymentTokenRepositoryInterface $tokenRepository
     * @param PaymentTokenManagement $paymentTokenManagement
     * @param EncryptorInterface $encryptor
     * @param PaytrailLogger $logger
     */
    public function __construct(
        private Session                         $session,
        private ProcessPayment                  $processPayment,
        private RequestInterface                $request,
        private ResultFactory                   $resultFactory,
        private Config                          $gatewayConfig,
        private FinnishReferenceNumber          $referenceNumber,
        private PaymentTokenFactory             $paymentTokenFactory,
        private SerializerInterface             $jsonSerializer,
        private PaymentTokenRepositoryInterface $tokenRepository,
        private PaymentTokenManagement          $paymentTokenManagement,
        private EncryptorInterface              $encryptor,
        private PaytrailLogger                  $logger
    ) {
    }

    /**
     * Execute function
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        try {
            $this->logger->logData(
                Logger::NOTICE,
                'Callback from Paytrail received' . PHP_EOL .
                'params: ' . json_encode($this->request->getParams(), JSON_PRETTY_PRINT)
            );

            $reference     = $this->request->getParam('checkout-reference');
            $response      = $this->resultFactory->create(ResultFactory::TYPE_JSON);
            $responseError = [];

            $order  = $this->referenceNumber->getOrderByReference($reference);
            $status = $order->getStatus();

            // save credit card
            if ($this->request->getParam('checkout-card-token')) {
                $this->processCardToken($this->request->getParams(), $order);
            }

            if ($status == 'pending_payment' || in_array($status, Receipt::ORDER_CANCEL_STATUSES)) {
                // order status could be changed by receipt
                // if not, status change needs to be forced by processing the payment
                $responseError = $this->processPayment->process($this->request->getParams(), $this->session);

                $this->logger->debugLog(
                    'request',
                    'PayAndAddCard callback response' . PHP_EOL .
                    'order status: ' . $status . PHP_EOL .
                    'orderNo: ' . $order->getId() . PHP_EOL .
                    'responseErrors: ' . json_encode($responseError, JSON_PRETTY_PRINT)
                );
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            $responseError['process_error'] = $e->getMessage();
        }

        return $response->setData([
            'success' => !$responseError ? 'true' : 'false',
            'error' => !$responseError ? '' : $responseError
        ]);
    }

    /**
     * Process token response to save credit card.
     *
     * @param array $params
     * @param Order $order
     *
     * @return void
     */
    public function processCardToken($params, $order)
    {
        $customerId   = $order->getCustomerId();
        $tokenDetails = $this->jsonSerializer->serialize(
            [
                'type'           => $this->cardTypes[$params['type']],
                'maskedCC'       => $params['partial_pan'],
                'expirationDate' => $params['expire_year'] . '/' . $params['expire_month']
            ]
        );

        $publicHash = $this->createPublicHash($params['type'], $customerId, $tokenDetails);

        $savedCard = $this->paymentTokenManagement->getByPublicHash($publicHash, $customerId);

        if ($savedCard) {
            $savedCard->setIsActive(true);
            $savedCard->setIsVisible(true);
            $this->tokenRepository->save($savedCard);
        } else {
            $this->saveToken($params, $publicHash, $customerId, $tokenDetails);
        }
    }

    /**
     * Save credit card from callback response data.
     *
     * @param array $params
     * @param string $publicHash
     * @param string $customerId
     * @param string $tokenDetails
     *
     * @return void
     */
    private function saveToken($params, $publicHash, $customerId, $tokenDetails)
    {
        $vaultPaymentToken = $this->paymentTokenFactory->create(PaymentTokenFactory::TOKEN_TYPE_CREDIT_CARD);
        $vaultPaymentToken->setCustomerId($customerId);
        $vaultPaymentToken->setPaymentMethodCode($this->gatewayConfig->getCcVaultCode());
        $vaultPaymentToken->setExpiresAt(
            $this->getExpiresDate($params['expire_month'], $params['expire_year'])
        );
        $vaultPaymentToken->setGatewayToken($params['checkout-card-token']);
        $vaultPaymentToken->setTokenDetails($tokenDetails);
        $vaultPaymentToken->setPublicHash($publicHash);
        $this->tokenRepository->save($vaultPaymentToken);
    }

    /**
     * Create public hash.
     *
     * @param string $cardType
     * @param string $customerId
     * @param string $tokenDetails
     *
     * @return string
     */
    private function createPublicHash($cardType, $customerId, $tokenDetails): string
    {
        return $this->encryptor->getHash(
            $customerId
            . Config::CC_VAULT_CODE
            . $this->cardTypes[$cardType]
            . $tokenDetails
        );
    }

    /**
     * Return expires date for credit card from month/year.
     *
     * @param string $expMonth
     * @param string $expYear
     *
     * @return string
     */
    private function getExpiresDate($expMonth, $expYear): string
    {
        $expiresDate = sprintf(
            '%s-%s-01',
            $expYear,
            $expMonth
        );

        return date("Y-m-t 23:59:59", strtotime($expiresDate));
    }
}
