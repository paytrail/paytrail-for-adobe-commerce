<?php

namespace Paytrail\PaymentService\Controller\Tokenization;

use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Model\PaymentTokenFactory;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Helper\ApiData;
use Paytrail\PaymentService\Helper\Data;
use Paytrail\SDK\Model\Token\Card;
use Psr\Log\LoggerInterface;

/**
 * Class SaveCard
 */
class SaveCard extends \Magento\Framework\App\Action\Action
{
    private const ADDING_CARD_SUCCESS = 'Card added successfully';

    /**
     * @var Data
     */
    private $opHelper;

    /**
     * @var $errorMsg
     */
    protected $errorMsg = null;

    /**
     * @var ApiData
     */
    protected $apiData;

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
     * @param Context $context
     * @param Data $opHelper
     * @param ApiData $apiData
     * @param CustomerSession $customerSession
     * @param PaymentTokenFactory $paymentTokenFactory
     * @param SerializerInterface $jsonSerializer
     * @param EncryptorInterface $encryptor
     * @param PaymentTokenRepositoryInterface $tokenRepository
     * @param LoggerInterface $logger
     * @param Config $gatewayConfig
     * @param PaymentTokenManagementInterface $paymentTokenManagementInterface
     * @param Session $checkoutSession
     */
    public function __construct(
        Context $context,
        Data $opHelper,
        ApiData $apiData,
        CustomerSession $customerSession,
        PaymentTokenFactory $paymentTokenFactory,
        SerializerInterface $jsonSerializer,
        EncryptorInterface $encryptor,
        PaymentTokenRepositoryInterface $tokenRepository,
        LoggerInterface $logger,
        Config $gatewayConfig,
        PaymentTokenManagementInterface $paymentTokenManagementInterface,
        Session $checkoutSession
    ) {
        parent::__construct($context);
        $this->opHelper = $opHelper;
        $this->apiData = $apiData;
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
     * execute method
     */
    public function execute()
    {
        /** @var string $tokenizationId */
        $tokenizationId = $this->getRequest()->getParam('checkout-tokenization-id');

        if (!$tokenizationId || $tokenizationId === '') {
            $this->checkoutSession->setData(
                'paytrail_previous_error',
                __('Card saving has been aborted. Please contact customer service.')
            );
            $this->redirect();
            return;
        }
        $responseData = $this->getResponseData($tokenizationId);
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
            $this->messageManager->addErrorMessage('This card has already been added to your vault');
            $this->logger->error($e->getMessage());
            $this->redirect();
            return;
        }

        // success
        $this->checkoutSession->setData('paytrail_previous_success', __(self::ADDING_CARD_SUCCESS));
        $this->redirect();
    }

    /**
     * @param $tokenizationId
     * @return mixed
     * @throws \Paytrail\PaymentService\Exceptions\CheckoutException
     */
    protected function getResponseData($tokenizationId)
    {
        $response = $this->apiData->processApiRequest(
            'token_request',
            null,
            null,
            null,
            null,
            $tokenizationId
        );

        $errorMsg = $response['error'];

        if (isset($errorMsg)) {
            $this->errorMsg = ($errorMsg);
            $this->opHelper->processError($errorMsg);
        }

        return $response["data"];
    }

    /**
     * @param $responseData
     */
    private function saveToken($responseData)
    {
        if (!$responseData) {
            $this->messageManager->addErrorMessage('There is a problem communicating with the provider');
            $this->logger->error('There is a problem communicating with the provider. Response data: ' . $responseData);
            $this->_redirect('checkout', ['_fragment' => 'payment']);
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

    /**
     * @return ResponseInterface
     */
    protected function redirect(): ResponseInterface
    {
        $customRedirectUrl = $this->_request->getParam('custom_redirect_url');

        return $customRedirectUrl
            ? $this->_redirect($customRedirectUrl)
            : $this->_redirect('checkout', ['_fragment' => 'payment']);
    }
}
