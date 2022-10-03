<?php
namespace Paytrail\PaymentService\Model;

use Magento\Framework\Model\AbstractModel;
use Paytrail\PaymentService\Api\Data\SubscriptionListResultInterface;

class SubscriptionListResult extends AbstractModel implements SubscriptionListResultInterface
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
     * @return \Paytrail\PaymentService\Api\Data\SubscriptionListResultInterface
     */
    public function setSubscriptionId($subscriptionId): self
    {
        $this->setData('subscription_id', $subscriptionId);

        return $this;
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->getData('status');
    }

    /**
     * @param $status
     * @return \Paytrail\PaymentService\Api\Data\SubscriptionListResultInterface
     */
    public function setStatus($status): self
    {
        $this->setData('status', $status);

        return $this;
    }

    /**
     * @return string
     */
    public function getNextOrderDate(): string
    {
        return $this->getData('next_order_date');
    }

    /**
     * @param $data
     * @return \Paytrail\PaymentService\Api\Data\SubscriptionListResultInterface
     */
    public function setNextOrderDate($data): self
    {
        $this->setData('next_order_date', $data);

        return $this;
    }

    /**
     * @return string
     */
    public function getRecurringProfileId(): string
    {
        return $this->getData('recurring_profile_id');
    }

    /**
     * @param $profileId
     * @return \Paytrail\PaymentService\Api\Data\SubscriptionListResultInterface
     */
    public function setRecurringProfileId($profileId): self
    {
        $this->setData('recurring_profile_id', $profileId);

        return $this;
    }

    /**
     * @return string
     */
    public function getUpdatedAt(): string
    {
        return $this->getData('updated_at');
    }

    /**
     * @param $data
     * @return \Paytrail\PaymentService\Api\Data\SubscriptionListResultInterface
     */
    public function setUpdatedAt($data): self
    {
        $this->setData('updated_at', $data);

        return $this;
    }

    /**
     * @return string
     */
    public function getRepeatCountLeft(): string
    {
        return $this->getData('repeat_count_left');
    }

    /**
     * @param $data
     * @return \Paytrail\PaymentService\Api\Data\SubscriptionListResultInterface
     */
    public function setRepeatCountLeft($data): self
    {
        $this->setData('repeat_count_left', $data);

        return $this;
    }

    /**
     * @return string
     */
    public function getRetryCount(): string
    {
        return $this->getData('retry_count');
    }

    /**
     * @param $data
     * @return \Paytrail\PaymentService\Api\Data\SubscriptionListResultInterface
     */
    public function setRetryCount($data): self
    {
        $this->setData('retry_count', $data);

        return $this;
    }

    /**
     * @return string
     */
    public function getSelectedToken(): string
    {
        return $this->getData('selected_token');
    }

    /**
     * @param $selectedToken
     * @return \Paytrail\PaymentService\Api\Data\SubscriptionListResultInterface
     */
    public function setSelectedToken($selectedToken): self
    {
        $this->setData('selected_token', $selectedToken);

        return $this;
    }
}
