<?php

namespace Paytrail\PaymentService\Model\Token;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Model\CcConfigProvider;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Magento\Vault\Model\PaymentTokenFactory;
use Paytrail\PaymentService\Api\CustomerTokensManagementInterface;
use Psr\Log\LoggerInterface;

class CustomerTokensManagement implements CustomerTokensManagementInterface
{
    /**
     * @var UserContextInterface
     */
    protected $userContext;

    /**
     * @var PaymentTokenManagementInterface
     */
    protected $paymentTokenManagement;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var Json
     */
    protected $jsonSerializer;

    /**
     * @var PaymentTokenFactory
     */
    protected $paymentTokenFactory;

    /**
     * @var CustomerTokensResultFactory
     */
    protected $customerTokensResultFactory;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var CcConfigProvider
     */
    protected $ccConfigProvider;

    /**
     * @param UserContextInterface $userContext
     * @param PaymentTokenManagementInterface $paymentTokenManagement
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param Json $jsonSerializer
     * @param PaymentTokenFactory $paymentTokenFactory
     * @param CustomerTokensResultFactory $customerTokensResultFactory
     * @param LoggerInterface $logger
     * @param CcConfigProvider $ccConfigProvider
     */
    public function __construct(
        UserContextInterface            $userContext,
        PaymentTokenManagementInterface $paymentTokenManagement,
        SearchCriteriaBuilder           $searchCriteriaBuilder,
        Json                            $jsonSerializer,
        PaymentTokenFactory             $paymentTokenFactory,
        CustomerTokensResultFactory     $customerTokensResultFactory,
        LoggerInterface                 $logger,
        CcConfigProvider                $ccConfigProvider
    ) {
        $this->userContext = $userContext;
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->jsonSerializer = $jsonSerializer;
        $this->paymentTokenFactory = $paymentTokenFactory;
        $this->customerTokensResultFactory = $customerTokensResultFactory;
        $this->logger = $logger;
        $this->ccConfigProvider = $ccConfigProvider;
    }

    /**
     * @return \Paytrail\PaymentService\Api\Data\CustomerTokensResultInterface[]
     * @throws LocalizedException
     */
    public function showCustomerPaymentMethods(): array
    {
        $customerId = $this->userContext->getUserId();
        if (!$customerId) {
            throw new LocalizedException(__('Customer is not authorized for this operation'));
        }

        $paymentTokens = [];
        try {
            $tokens = $this->paymentTokenManagement->getListByCustomerId($customerId);
            $i = 0;
            foreach ($tokens as $token) {
                if ($token->getIsActive() && $token->getIsVisible()) {
                    $paymentTokens[] = $this->customerTokensResultFactory->create();
                    $paymentTokens[$i]
                        ->setEntityId($token->getId())
                        ->setCustomerId($customerId)
                        ->setPublicHash((string)$token->getPublicHash())
                        ->setType($token->getType())
                        ->setPaymentMethodCode($token->getPaymentMethodCode())
                        ->setCreatedAt($token->getCreatedAt())
                        ->setExpiresAt($token->getExpiresAt())
                        ->setCardType($this->jsonSerializer->unserialize($token->getTokenDetails())['type'])
                        ->setCardIcon($this->ccConfigProvider
                            ->getIcons()[$this->jsonSerializer->unserialize($token->getTokenDetails())['type']]['url'])
                        ->setMaskedCC($this->jsonSerializer->unserialize($token->getTokenDetails())['maskedCC']);
                    $i++;
                }
            }

            return $paymentTokens;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new LocalizedException(__("Payment methods can't be shown"));
        }
    }
}
