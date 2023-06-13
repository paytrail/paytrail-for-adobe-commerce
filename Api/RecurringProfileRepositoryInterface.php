<?php
namespace Paytrail\PaymentService\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

interface RecurringProfileRepositoryInterface
{
    /**
     * @param $profileId
     * @return Data\RecurringProfileInterface
     * @throws NoSuchEntityException
     */
    public function get($profileId);

    /**
     * @param Data\RecurringProfileInterface $profile
     * @return Data\RecurringProfileInterface
     * @throws CouldNotSaveException
     */
    public function save(Data\RecurringProfileInterface $profile);

    /**
     * @param Data\RecurringProfileInterface $profile
     * @throws CouldNotDeleteException
     */
    public function delete(Data\RecurringProfileInterface $profile);

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return Data\RecurringProfileSearchResultInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): Data\RecurringProfileSearchResultInterface;
}
