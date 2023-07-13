<?php

namespace Paytrail\PaymentService\Model\Subscription;

class ActiveOrderProvider
{
    /**
     * @var \Paytrail\PaymentService\Model\ResourceModel\Subscription\SubscriptionLink\CollectionFactory
     */
    private $linkFactory;
    private \Magento\Sales\Model\Order\Config $orderConfig;

    public function __construct(
        \Paytrail\PaymentService\Model\ResourceModel\Subscription\SubscriptionLink\CollectionFactory $collectionFactory,
        \Magento\Sales\Model\Order\Config $orderConfig
    ) {
        $this->linkFactory = $collectionFactory;
        $this->orderConfig = $orderConfig;
    }

    /**
     * @return int[]
     */
    public function getPayableOrderIds()
    {
        return $this->getCollection()->getColumnValues('order_id');
    }

    /**
     * @return \Paytrail\PaymentService\Model\ResourceModel\Subscription\SubscriptionLink\Collection
     */
    private function getCollection(): \Paytrail\PaymentService\Model\ResourceModel\Subscription\SubscriptionLink\Collection
    {
        /** @var \Paytrail\PaymentService\Model\ResourceModel\Subscription\SubscriptionLink\Collection $subscriptionLinks */
        $subscriptionLinks = $this->linkFactory->create();
        $subscriptionLinks->join(
            ['sub' =>\Paytrail\PaymentService\Model\ResourceModel\Subscription::PAYTRAIL_SUBSCRIPTIONS_TABLENAME],
            'main_table.subscription_id = sub.entity_id',
        );
        $subscriptionLinks->join(
            'sales_order',
            'main_table.order_id = sales_order.entity_id'
        );
        $select = $subscriptionLinks->getSelect();
        $select->where(
            'sub.status IN (?)',
            \Paytrail\PaymentService\Api\Data\SubscriptionInterface::CLONEABLE_STATUSES
        );
        $select->where(
            'sales_order.status IN (?)',
            $this->orderConfig->getStateDefaultStatus(
                \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT
            )
        );

        $currentDate = new \DateTime();
        $select->where(
            'sub.next_order_date <= ?',
            $currentDate->format('Y-m-d H:i:s')
        );

        return $subscriptionLinks;
    }
}
