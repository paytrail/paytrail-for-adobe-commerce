<?php

namespace Paytrail\PaymentService\Model\Token;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Model\CcConfigProvider;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Paytrail\PaymentService\Api\CustomerTokensManagementInterface;
use Paytrail\PaymentService\Api\Data\CustomerTokensResultInterface;
use Psr\Log\LoggerInterface;

class CustomerTokensManagement implements CustomerTokensManagementInterface
{
    /**
     * @param UserContextInterface $userContext
     * @param PaymentTokenManagementInterface $paymentTokenManagement
     * @param Json $jsonSerializer
     * @param CustomerTokensResultFactory $customerTokensResultFactory
     * @param LoggerInterface $logger
     * @param CcConfigProvider $ccConfigProvider
     */
    public function __construct(
       private UserContextInterface            $userContext,
       private PaymentTokenManagementInterface $paymentTokenManagement,
       private Json                            $jsonSerializer,
       private CustomerTokensResultFactory     $customerTokensResultFactory,
       private LoggerInterface                 $logger,
       private CcConfigProvider                $ccConfigProvider
    ) {
    }

    /**
     * @return CustomerTokensResultInterface[]
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
