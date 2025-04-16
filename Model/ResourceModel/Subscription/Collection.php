<?php
namespace Paytrail\PaymentService\Model\ResourceModel\Subscription;

use Magento\Framework\Api\ExtensibleDataInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Paytrail\PaymentService\Api\Data\SubscriptionSearchResultInterface;

class Collection extends AbstractCollection implements SubscriptionSearchResultInterface
{
    /**
     * @var SearchCriteriaInterface
     */
    private SearchCriteriaInterface $searchCriteria;

    /**
     * Initialize subscription model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            \Paytrail\PaymentService\Model\Subscription::class,
            \Paytrail\PaymentService\Model\ResourceModel\Subscription::class
        );
    }

    /**
     * Set items list.
     *
     * @param ExtensibleDataInterface[]|null $items
     * @return $this
     * @throws \Exception
     */
    public function setItems(?array $items = null)
    {
        if (!$items) {
            return $this;
        }
        foreach ($items as $item) {
            $this->addItem($item);
        }
        return $this;
    }

    /**
     * Get search criteria.
     *
     * @return SearchCriteriaInterface|null
     */
    public function getSearchCriteria()
    {
        return $this->searchCriteria;
    }

    /**
     * Set search criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return $this
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function setSearchCriteria(SearchCriteriaInterface $searchCriteria)
    {
        $this->searchCriteria = $searchCriteria;

        return $this;
    }

    /**
     * Get total count.
     *
     * @return int
     */
    public function getTotalCount()
    {
        return $this->getSize();
    }

    /**
     * Set total count.
     *
     * @param int $totalCount
     * @return $this
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function setTotalCount(int $totalCount)
    {
        // total count is the collections size, do not modify it.
        return $this;
    }

    /**
     * Get billing collection for orders
     *
     * @param int[] $orderIds
     *
     * @return $this
     */
    public function getBillingCollectionByOrderIds(array $orderIds)
    {
        $this->join(
            ['links' => SubscriptionLink::LINK_TABLE_NAME],
            $this->getConnection()->quoteInto(
                'links.subscription_id = main_table.entity_id AND links.order_id IN (?)',
                $orderIds
            ),
            ['order_id']
        );

        $this->join(
            ['token' => 'vault_payment_token'],
            'token.entity_id = main_table.selected_token',
            [
                'token' => 'public_hash',
                'token_active' => 'is_active',
                'token_visible' => 'is_visible'
            ]
        );

        return $this;
    }
}
