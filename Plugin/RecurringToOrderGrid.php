<?php

namespace Paytrail\PaymentService\Plugin;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\ResourceModel\Order\Grid\Collection;

class RecurringToOrderGrid
{
    /**
     * @param Collection $subject
     * @return null
     * @throws LocalizedException
     */
    public function beforeLoad(Collection $subject)
    {
        if (!$subject->isLoaded()) {
            $primaryKey = $subject->getResource()->getIdFieldName();
            $tableName = $subject->getResource()->getTable('paytrail_subscription_link');

            $subject->getSelect()->joinLeft(
                $tableName,
                'main_table.' . $primaryKey . ' = ' . $tableName . '.order_id',
                'subscription_id'
            );

            $subject->getSelect()->joinLeft(
                $subject->getResource()->getTable('paytrail_subscriptions'),
                $tableName . '.subscription_id = paytrail_subscriptions.entity_id',
                ['recurring_status' => 'paytrail_subscriptions.status']
            );

            $subject->getSelect()->joinLeft(
                $subject->getResource()->getTable('recurring_payment_profiles'),
                'paytrail_subscriptions.recurring_profile_id = recurring_payment_profiles.profile_id',
                ['recurring_profile' => 'recurring_payment_profiles.name']
            );
        }

        return null;
    }
}
