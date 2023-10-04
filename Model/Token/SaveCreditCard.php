<?php

namespace Paytrail\PaymentService\Model\Token;

use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Payment\Gateway\Command\CommandManagerPoolInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Model\PaymentTokenFactory;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Model\Receipt\ProcessService;
use Paytrail\SDK\Model\Token\Card;
use Psr\Log\LoggerInterface;

class SaveCreditCard
{
    private const ADDING_CARD_SUCCESS = 'Card added successfully';

    /**
     * @var $errorMsg
     */
    protected $errorMsg = null;

    /**
     * @var CustomerSession
     */
    private $customerSession;
    /**
     * @var PaymentTokenFactory
     */
    private $paymentTokenFactory;

    /**
     * @var SerializerInterface
     */
    private $jsonSerializer;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @var PaymentTokenRepositoryInterface
     */
    private $tokenRepository;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Config
     */
    protected $gatewayConfig;

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
     * @var PaymentTokenManagementInterface
     */
    private $paymentTokenManagementInterface;

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * SaveCreditCard constructor.
     *
     * @param CustomerSession $customerSession
     * @param PaymentTokenFactory $paymentTokenFactory
     * @param SerializerInterface $jsonSerializer
     * @param EncryptorInterface $encryptor
     * @param PaymentTokenRepositoryInterface $tokenRepository
     * @param LoggerInterface $logger
     * @param Config $gatewayConfig
     * @param PaymentTokenManagementInterface $paymentTokenManagementInterface
     * @param Session $checkoutSession
     * @param CommandManagerPoolInterface $commandManagerPool
     * @param ProcessService $processService
     */
    public function __construct(
        CustomerSession $customerSession,
        PaymentTokenFactory $paymentTokenFactory,
        SerializerInterface $jsonSerializer,
        EncryptorInterface $encryptor,
        PaymentTokenRepositoryInterface $tokenRepository,
        LoggerInterface $logger,
        Config $gatewayConfig,
        PaymentTokenManagementInterface $paymentTokenManagementInterface,
        Session $checkoutSession,
        private CommandManagerPoolInterface $commandManagerPool,
        private ProcessService $processService
    ) {
        $this->customerSession = $customerSession;
        $this->paymentTokenFactory = $paymentTokenFactory;
        $this->jsonSerializer = $jsonSerializer;
        $this->encryptor = $encryptor;
        $this->tokenRepository = $tokenRepository;
        $this->logger = $logger;
        $this->gatewayConfig = $gatewayConfig;
        $this->paymentTokenManagementInterface = $paymentTokenManagementInterface;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @param $token
     * @return void
     * @throws \Paytrail\PaymentService\Exceptions\CheckoutException
     */
    public function saveCard($token)
    {
        $responseData = $this->getResponseData($token);
        try {
            $customerId = $this->customerSession->getCustomerId();
            $paymentToken = $this->paymentTokenManagementInterface->getByPublicHash(
                $this->createPublicHash($responseData->getCard(), $customerId),
                $customerId
            );

            if ($paymentToken) {
                $paymentToken->setIsVisible(true);
                $paymentToken->setIsActive(true);
                $paymentToken->setGatewayToken($responseData->getToken());
                $this->tokenRepository->save($paymentToken);
            } else {
                $this->saveToken($responseData);
            }
        } catch (\Magento\Framework\Exception\AlreadyExistsException $e) {
//            $this->messageManager->addErrorMessage('This card has already been added to your vault');
            $this->logger->error($e->getMessage());
        }

        // success
        $this->checkoutSession->setData('paytrail_previous_success', __(self::ADDING_CARD_SUCCESS));
    }

    /**
     * @param $tokenizationId
     * @return mixed
     * @throws \Paytrail\PaymentService\Exceptions\CheckoutException
     */
    protected function getResponseData($tokenizationId)
    {
        $commandExecutor = $this->commandManagerPool->get('paytrail');
        $response = $commandExecutor->executeByCode(
            'token_request',
            null,
            [
                'tokenization_id' => $tokenizationId
            ]
        );

        $errorMsg = $response['error'];

        if (isset($errorMsg)) {
            $this->errorMsg = ($errorMsg);
            $this->processService->processError($errorMsg);
        }

        return $response["data"];
    }

    /**
     * @param $responseData
     */
    private function saveToken($responseData)
    {
        if (!$responseData) {
//            $this->messageManager->addErrorMessage('There is a problem communicating with the provider');
            $this->logger->error('There is a problem communicating with the provider. Response data: ' . $responseData);
            return;
        }
        /** @var string $gatewayToken */
        $gatewayToken = $responseData->getToken();
        /** @var Card $cardData */
        $cardData = $responseData->getCard();

        $vaultPaymentToken = $this->paymentTokenFactory->create(PaymentTokenFactory::TOKEN_TYPE_CREDIT_CARD);
        $customerId = $this->customerSession->getCustomer()->getId();
        $vaultPaymentToken->setCustomerId($customerId);
        $vaultPaymentToken->setPaymentMethodCode($this->gatewayConfig->getCcVaultCode());
        $vaultPaymentToken->setExpiresAt(
            sprintf(
                '%s-%s-01 00:00:00',
                $cardData->getExpireYear(),
                $cardData->getExpireMonth()
            )
        );
        $tokenDetails = $this->jsonSerializer->serialize(
            [
                'type' => $this->cardTypes[$cardData->getType()],
                'maskedCC' => $cardData->getPartialPan(),
                'expirationDate' => $cardData->getExpireYear() . '/' . $cardData->getExpireMonth()
            ]
        );
        $vaultPaymentToken->setGatewayToken($gatewayToken);
        $vaultPaymentToken->setTokenDetails($tokenDetails);
        $vaultPaymentToken->setPublicHash($this->createPublicHash($cardData, $customerId));
        $this->tokenRepository->save($vaultPaymentToken);
    }

    /**
     * @param $addingCard
     * @param $customerId
     * @return string
     */
    private function createPublicHash($addingCard, $customerId)
    {
        $tokenDetails = $this->jsonSerializer->serialize(
            [
                'type' => $this->cardTypes[$addingCard->getType()],
                'maskedCC' => $addingCard->getPartialPan(),
                'expirationDate' => $addingCard->getExpireYear() . '/' . $addingCard->getExpireMonth()
            ]
        );
        return $this->encryptor->getHash(
            $customerId
            . Config::CC_VAULT_CODE
            . $this->cardTypes[$addingCard->getType()]
            . $tokenDetails
        );
    }
}
