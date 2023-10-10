<?php

namespace Paytrail\PaymentService\Controller\Callback;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Model\PaymentTokenFactory;
use Paytrail\PaymentService\Controller\Receipt\Index as Receipt;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Model\Receipt\ProcessPayment;

class PayAndAddCardCallback implements \Magento\Framework\App\ActionInterface
{
    /**
     * @var string[]
     */
    protected $cardTypes = [
        'Visa' => 'VI',
        'MasterCard' => 'MC',
        'Discover' => 'DI',
        'Amex' => 'AE',
        'Maestro' => 'SM',
        'Solo' => 'SO'
    ];

    /**
     * PayAndAddCardCallback constructor.
     *
     * @param Session $session
     * @param ProcessPayment $processPayment
     * @param RequestInterface $request
     * @param ResultFactory $resultFactory
     * @param Config $gatewayConfig
     * @param OrderFactory $orderFactory
     * @param PaymentTokenFactory $paymentTokenFactory
     * @param SerializerInterface $jsonSerializer
     * @param PaymentTokenRepositoryInterface $tokenRepository
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        private Session $session,
        private ProcessPayment $processPayment,
        private RequestInterface $request,
        private ResultFactory $resultFactory,
        private Config $gatewayConfig,
        private OrderFactory $orderFactory,
        private PaymentTokenFactory $paymentTokenFactory,
        private SerializerInterface $jsonSerializer,
        private PaymentTokenRepositoryInterface $tokenRepository,
        private EncryptorInterface $encryptor
    ) {
    }

    /**
     * Execute function
     *
     * @return ResultInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(): ResultInterface
    {
        $reference = $this->request->getParam('checkout-reference');
        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        /** @var string $orderNo */
        $orderNo = $this->gatewayConfig->getGenerateReferenceForOrder()
            ? $this->gatewayConfig->getIdFromOrderReferenceNumber($reference)
            : $reference;

        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->orderFactory->create()->loadByIncrementId($orderNo);
        $status = $order->getStatus();

        // save credit card
        if ($this->request->getParam('checkout-card-token')) {
            $this->saveToken($this->request->getParams(), $order);
        }

        if ($status == 'pending_payment' || in_array($status, Receipt::ORDER_CANCEL_STATUSES)) {
            // order status could be changed by receipt
            // if not, status change needs to be forced by processing the payment
            $response['error'] = $this->processPayment->process($this->request->getParams(), $this->session);
        }

        return $response;
    }

    /**
     * Save credit card from callback response data.
     *
     * @param array $params
     * @param Order $order
     * @return void
     */
    private function saveToken($params, $order)
    {
        $vaultPaymentToken = $this->paymentTokenFactory->create(PaymentTokenFactory::TOKEN_TYPE_CREDIT_CARD);
        $customerId = $order->getCustomerId();
        $vaultPaymentToken->setCustomerId($customerId);
        $vaultPaymentToken->setPaymentMethodCode($this->gatewayConfig->getCcVaultCode());
        $vaultPaymentToken->setExpiresAt(
            sprintf(
                '%s-%s-01 00:00:00',
                $params['expire_year'],
                $params['expire_month']
            )
        );
        $tokenDetails = $this->jsonSerializer->serialize(
            [
                'type' => $this->cardTypes[$params['type']],
                'maskedCC' => $params['partial_pan'],
                'expirationDate' => $params['expire_year'] . '/' . $params['expire_month']
            ]
        );
        $vaultPaymentToken->setGatewayToken($params['checkout-card-token']);
        $vaultPaymentToken->setTokenDetails($tokenDetails);
        $vaultPaymentToken->setPublicHash($this->createPublicHash($params['type'], $customerId, $tokenDetails));
        $this->tokenRepository->save($vaultPaymentToken);
    }

    /**
     * Create public hash.
     *
     * @param string $cardType
     * @param string $customerId
     * @param string $tokenDetails
     * @return string
     */
    private function createPublicHash($cardType, $customerId, $tokenDetails)
    {
        return $this->encryptor->getHash(
            $customerId
            . Config::CC_VAULT_CODE
            . $this->cardTypes[$cardType]
            . $tokenDetails
        );
    }
}
