<?php

namespace Paytrail\PaymentService\Model;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Paytrail\PaymentService\Api\CardManagementInterface;
use Paytrail\PaymentService\Api\Data\SubscriptionInterface;
use Paytrail\PaymentService\Api\Data\SubscriptionSearchResultInterface;
use Paytrail\PaymentService\Api\SubscriptionRepositoryInterface;
use Paytrail\PaymentService\Helper\ApiData;
use Paytrail\SDK\Exception\ValidationException;

class CardManagement implements CardManagementInterface
{
    /**
     * @var ApiData
     */
    private ApiData $apiData;

    /**
     * @var PaymentTokenRepositoryInterface
     */
    private PaymentTokenRepositoryInterface $paymentTokenRepository;

    /**
     * @var UserContextInterface
     */
    private UserContextInterface $userContext;

    /**
     * @var FilterBuilder
     */
    private FilterBuilder $filterBuilder;

    /**
     * @var FilterGroupBuilder
     */
    private FilterGroupBuilder $filterGroupBuilder;

    /**
     * @var SubscriptionRepositoryInterface $subscriptionRepository
     */
    private SubscriptionRepositoryInterface $subscriptionRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private SearchCriteriaBuilder $searchCriteriaBuilder;

    /**
     * @param ApiData $apiData
     * @param PaymentTokenRepositoryInterface $paymentTokenRepository
     * @param UserContextInterface $userContext
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     * @param SubscriptionRepositoryInterface $subscriptionRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        ApiData $apiData,
        PaymentTokenRepositoryInterface $paymentTokenRepository,
        UserContextInterface $userContext,
        FilterBuilder $filterBuilder,
        FilterGroupBuilder $filterGroupBuilder,
        SubscriptionRepositoryInterface $subscriptionRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->apiData = $apiData;
        $this->paymentTokenRepository = $paymentTokenRepository;
        $this->userContext = $userContext;
        $this->filterBuilder = $filterBuilder;
        $this->filterGroupBuilder = $filterGroupBuilder;
        $this->subscriptionRepository = $subscriptionRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * @inheritdoc
     */
    public function generateAddCardUrl(): string
    {
        $response = $this->apiData->processApiRequest('add_card');

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
