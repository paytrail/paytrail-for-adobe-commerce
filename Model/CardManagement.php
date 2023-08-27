<?php

namespace Paytrail\PaymentService\Model;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Command\CommandManagerPoolInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Paytrail\PaymentService\Api\CardManagementInterface;
use Paytrail\PaymentService\Api\Data\SubscriptionInterface;
use Paytrail\PaymentService\Api\Data\SubscriptionSearchResultInterface;
use Paytrail\PaymentService\Api\SubscriptionRepositoryInterface;
use Paytrail\SDK\Exception\ValidationException;

class CardManagement implements CardManagementInterface
{
    /**
     * CardManagement constructor.
     *
     * @param PaymentTokenRepositoryInterface $paymentTokenRepository
     * @param UserContextInterface $userContext
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     * @param SubscriptionRepositoryInterface $subscriptionRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param CommandManagerPoolInterface $commandManagerPool
     */
    public function __construct(
        private PaymentTokenRepositoryInterface $paymentTokenRepository,
        private UserContextInterface $userContext,
        private FilterBuilder $filterBuilder,
        private FilterGroupBuilder $filterGroupBuilder,
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private SearchCriteriaBuilder $searchCriteriaBuilder,
        private CommandManagerPoolInterface $commandManagerPool
    ) {
    }

    /**
     * @inheritdoc
     */
    public function generateAddCardUrl(): string
    {
        $commandExecutor = $this->commandManagerPool->get('paytrail');
        $response = $commandExecutor->executeByCode('add_card');

        if (isset($response['error'])) {
            throw new ValidationException($response['error']);
        }

        return $response['data']->getHeader('Location')[0];
    }

    /**
     * @inheritdoc
     */
    public function delete(string $cardId): bool
    {
        $paymentToken = $this->paymentTokenRepository->getById((int)$cardId);
        if (!$paymentToken || (int)$paymentToken->getCustomerId() !== $this->userContext->getUserId()) {
            throw new LocalizedException(__('Card not found'));
        }

        $subscriptionWithCard = $this->getSubscriptionForPaymentToken($paymentToken);
        if ($subscriptionWithCard->getTotalCount()) {
            throw new LocalizedException(__('The card has active subscriptions'));
        }

        $this->paymentTokenRepository->delete($paymentToken);

        return true;
    }

    /**
     * Get subscription for payment token.
     *
     * @param PaymentTokenInterface $paymentToken
     * @return SubscriptionSearchResultInterface
     */
    private function getSubscriptionForPaymentToken(
        PaymentTokenInterface $paymentToken
    ): SubscriptionSearchResultInterface {
        $selectedTokenFilter = $this->filterBuilder
            ->setField('selected_token')
            ->setValue($paymentToken->getEntityId())
            ->setConditionType('eq')
            ->create();

        $statusFilter = $this->filterBuilder
            ->setField('status')
            ->setValue(SubscriptionInterface::STATUS_ACTIVE)
            ->setConditionType('eq')
            ->create();

        $statusFilterGroup = $this->filterGroupBuilder->addFilter($statusFilter)->create();
        $selectedTokenFilterGroup = $this->filterGroupBuilder->addFilter($selectedTokenFilter)->create();

        $searchCriteria = $this->searchCriteriaBuilder->setFilterGroups([$statusFilterGroup, $selectedTokenFilterGroup])
            ->create();

        return $this->subscriptionRepository->getList($searchCriteria);
    }
}
