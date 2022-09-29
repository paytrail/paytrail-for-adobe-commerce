<?php

namespace Paytrail\PaymentService\Model;

use Magento\Customer\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Psr\Log\LoggerInterface;

class CustomerPaymentMethodsManagement
{
    /**
     * @var Session
     */
    protected $customerSession;

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
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param Session $customerSession
     * @param PaymentTokenManagementInterface $paymentTokenManagement
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param Json $jsonSerializer
     * @param LoggerInterface $logger
     */
    public function __construct(
        Session                         $customerSession,
        PaymentTokenManagementInterface $paymentTokenManagement,
        SearchCriteriaBuilder           $searchCriteriaBuilder,
        Json                            $jsonSerializer,
        LoggerInterface                 $logger
    )
    {
        $this->customerSession = $customerSession;
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->jsonSerializer = $jsonSerializer;
        $this->logger = $logger;
    }

    /**
     * @return array|void
     * @throws LocalizedException
     */
    public function showCustomerPaymentMethods()
    {
        try {
            if ($this->customerSession->isLoggedIn()) {
                $customerId = $this->customerSession->getCustomerId();
                $tokens = $this->paymentTokenManagement->getListByCustomerId($customerId);
                $paymentMethods = [];

                foreach ($tokens as $token) {
                    if ($token->getIsActive() && $token->getIsVisible()) {
                        $paymentMethods[] = [
                            'customer' => $customerId,
                            'type' => $token->getType(),
                            'payment_method_code' => $token->getPaymentMethodCode(),
                            'created_at' => $token->getCreatedAt(),
                            'expires_at' => $token->getExpiresAt(),
                            'card_type' => $this->jsonSerializer->unserialize($token->getTokenDetails())['type'],
                            'maskedCC' => $this->jsonSerializer->unserialize($token->getTokenDetails())['maskedCC']
                        ];
                    }
                }

                $jsonArray = [];
                foreach ($paymentMethods as $paymentMethod) {
                    $jsonArray[] = $this->jsonSerializer->serialize($paymentMethod);
                }

                return $jsonArray;
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new LocalizedException(__("Payment methods can't be shown"));
        }
    }
}
