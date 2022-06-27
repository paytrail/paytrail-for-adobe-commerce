<?php

namespace Paytrail\PaymentService\Model\ResourceModel\Subscription;

use Magento\Framework\Model\ResourceModel\Db\VersionControl\AbstractDb;
use Paytrail\PaymentService\Api\Data\SubscriptionLinkInterface;

class SubscriptionLink extends AbstractDb
{
    public const LINK_TABLE_NAME = 'paytrail_subscription_link';
    protected $_eventPrefix = 'subscription_link';

    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init(self::LINK_TABLE_NAME, SubscriptionLinkInterface::FIELD_LINK_ID);
    }
}
