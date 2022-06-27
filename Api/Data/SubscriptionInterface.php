<?php
namespace Paytrail\PaymentService\Api\Data;

interface SubscriptionInterface
{
    const FIELD_ENTITY_ID = 'entity_id';
    const FIELD_CUSTOMER_ID = 'customer_id';
    const FIELD_STATUS = 'status';
    const FIELD_NEXT_ORDER_DATE = 'next_order_date';
    const FIELD_RECURRING_PROFILE_ID = 'recurring_profile_id';
    const FIELD_UPDATED_AT = 'updated_at';
    const FIELD_END_DATE = 'end_date';
    const FIELD_REPEAT_COUNT_LEFT = 'repeat_count_left';
    const FIELD_RETRY_COUNT = 'retry_count';
    const FIELD_SELECTED_TOKEN = 'selected_token';

    const STATUS_PENDING_PAYMENT = 'pending_payment';
    const STATUS_ACTIVE = 'active';
    const STATUS_CLOSED = 'closed';
    const STATUS_FAILED = 'failed';
    const STATUS_RESCHEDULED = 'rescheduled';

    const CLONEABLE_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_RESCHEDULED,
    ];

    /**
     * @return int
     */
    public function getId();

    /**
     * @return int
     */
    public function getCustomerId();

    /**
     * @return string
     */
    public function getStatus() : string;

    /**
     * @return string
     */
    public function getNextOrderDate() : string;

    /**
     * @return int
     */
    public function getRecurringProfileId() : int;

    /**
     * @return string
     */
    public function getUpdatedAt() : string;

    /**
     * @return string
     */
    public function getEndDate() : string;

    /**
     * @return int
     */
    public function getRepeatCountLeft() : int;

    /**
     * @return int
     */
    public function getRetryCount() : int;

    /**
     * @return int
     */
    public function getSelectedToken() : int;

    /**
     * @param int $entityId
     * @return $this
     */
    public function setId(int $entityId) : self;

    /**
     * @param int $customerId
     * @return $this
     */
    public function setCustomerId(int $customerId) : self;

    /**
     * @param $status
     * @return $this
     */
    public function setStatus($status) : self;

    /**
     * @param string $date
     * @return $this
     */
    public function setNextOrderDate(string $date) : self;

    /**
     * @param int $profileId
     * @return $this
     */
    public function setRecurringProfileId(int $profileId) : self;

    /**
     * @param string $updatedAt
     * @return $this
     */
    public function setUpdatedAt(string $updatedAt) : self;

    /**
     * @param string $endDate
     * @return $this
     */
    public function setEndDate(string $endDate) : self;

    /**
     * How many times payment will be processed before it ends.
     *
     * @param int $count
     * @return mixed
     */
    public function setRepeatCountLeft(int $count) : self;

    /**
     * How many times a failed payment has been retried.
     *
     * @param int $count
     * @return $this
     */
    public function setRetryCount(int $count) : self;

    /**
     * @param int $tokenId
     * @return $this
     */
    public function setSelectedToken(int $tokenId) : self;
}
