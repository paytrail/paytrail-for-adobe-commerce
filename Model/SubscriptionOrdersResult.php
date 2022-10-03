<?php
namespace Paytrail\PaymentService\Model;

use Magento\Framework\Model\AbstractModel;
use Paytrail\PaymentService\Api\Data\SubscriptionOrdersResultInterface;

class SubscriptionOrdersResult extends AbstractModel implements SubscriptionOrdersResultInterface
{
    /**
     * @return string
     */
    public function getSubscriptionId(): string
    {
        return $this->getData('subscription_id');
    }

    /**
     * @param $subscriptionId
     * @return \Paytrail\PaymentService\Api\Data\SubscriptionOrdersResultInterface
     */
    public function setSubscriptionId($subscriptionId): self
    {
        $this->setData('subscription_id', $subscriptionId);

        return $this;
    }
}
