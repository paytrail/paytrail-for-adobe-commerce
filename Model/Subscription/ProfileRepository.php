<?php

namespace Paytrail\PaymentService\Model\Subscription;

use Magento\CatalogImportExport\Model\Import\Proxy\Product\ResourceModel;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Paytrail\PaymentService\Api\Data;
use Paytrail\PaymentService\Api\Data\SubscriptionSearchResultInterface;
use Paytrail\PaymentService\Api\RecurringProfileRepositoryInterface;
use Paytrail\PaymentService\Model\Subscription;

class ProfileRepository implements RecurringProfileRepositoryInterface
{
    /**
     * @var \Paytrail\PaymentService\Model\ResourceModel\Subscription\Profile
     */
    private $profileResource;
    /**
     * @var Data\RecurringProfileInterfaceFactory
     */
    private $profileFactory;
    /**
     * @var \Paytrail\PaymentService\Model\ResourceModel\Subscription\Profile
     */
    private $profileResultFactory;
    /**
     * @var \Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface
     */
    private $collectionProcessor;

    public function __construct(
        \Paytrail\PaymentService\Model\ResourceModel\Subscription\Profile              $profileResource,
        \Paytrail\PaymentService\Api\Data\RecurringProfileInterfaceFactory             $profileFactory,
        \Paytrail\PaymentService\Api\Data\RecurringProfileSearchResultInterfaceFactory $profileResultFactory,
        \Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface $collectionProcessor
    ) {
        $this->profileResource = $profileResource;
        $this->profileFactory = $profileFactory;
        $this->profileResultFactory = $profileResultFactory;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * @inheritDoc
     */
    public function get($profileId)
    {
        /** @var Subscription $subscription */
        $subscription = $this->profileFactory->create();
        $this->profileResource->load($subscription, $profileId);

        if (!$subscription->getId()) {
            throw new NoSuchEntityException(\__(
                'No subscription profile found with id %id',
                [
                    'id' => $profileId
                ]
            ));
        }

        return $subscription;
    }

    /**
     * @inheritDoc
     */
    public function save(Data\RecurringProfileInterface $profile)
    {
        try {
            $this->profileResource->save($profile);
        } catch (\Throwable $e) {
            throw new CouldNotSaveException(\__(
                'Could not save Recurring Profile: %error',
                ['error' => $e->getMessage()]
            ));
        }

        return $profile;
    }

    /**
     * @inheritDoc
     */
    public function delete(Data\RecurringProfileInterface $profile)
    {
        try {
            $this->profileResource->delete($profile);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__(
                'Unable to delete recurring profile: %error',
                ['error' => $e->getMessage()]
            ));
        }
    }

    /**
     * @inheritDoc
     */
    public function getList(
        \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
    ) : Data\RecurringProfileSearchResultInterface {
        /** @var Data\RecurringProfileSearchResultInterface $searchResult */
        $searchResult = $this->profileResultFactory->create();
        $this->collectionProcessor->process($searchCriteria, $searchResult);
        $searchResult->setSearchCriteria($searchCriteria);

        return $searchResult;
    }
}
