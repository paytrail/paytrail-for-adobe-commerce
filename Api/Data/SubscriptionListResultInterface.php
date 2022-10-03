<?php

namespace Paytrail\PaymentService\Api\Data;

interface SubscriptionListResultInterface
{
    /**
     * @return string
     */
    public function getSubscriptionId(): string;

    /**
     * @param $subscriptionId
     * @return \Paytrail\PaymentService\Api\Data\SubscriptionListResultInterface
     */
    public function setSubscriptionId($subscriptionId): self;


    /**
     * @return string
     */
    public function getStatus(): string;

    /**
     * @param $status
     * @return \Paytrail\PaymentService\Api\Data\SubscriptionListResultInterface
     */
    public function setStatus($status): self;

    /**
     * @return string
     */
    public function getNextOrderDate(): string;


    /**
     * @param $data
     * @return \Paytrail\PaymentService\Api\Data\SubscriptionListResultInterface
     */
    public function setNextOrderDate($data): self;

    /**
     * @return string
     */
    public function getRecurringProfileId(): string;

    /**
     * @param $profileId
     * @return \Paytrail\PaymentService\Api\Data\SubscriptionListResultInterface
     */
    public function setRecurringProfileId($profileId): self;

    /**
     * @return string
     */
    public function getUpdatedAt(): string;

    /**
     * @param $data
     * @return \Paytrail\PaymentService\Api\Data\SubscriptionListResultInterface
     */
    public function setUpdatedAt($data): self;

    /**
     * @return string
     */
    public function getRepeatCountLeft(): string;

    /**
     * @param $data
     * @return \Paytrail\PaymentService\Api\Data\SubscriptionListResultInterface
     */
    public function setRepeatCountLeft($data): self;

    /**
     * @return string
     */
    public function getRetryCount(): string;

    /**
     * @param $data
     * @return \Paytrail\PaymentService\Api\Data\SubscriptionListResultInterface
     */
    public function setRetryCount($data): self;

    /**
     * @return string
     */
    public function getSelectedToken(): string;

    /**
     * @param $selectedToken
     * @return \Paytrail\PaymentService\Api\Data\SubscriptionListResultInterface
     */
    public function setSelectedToken($selectedToken): self;
}
