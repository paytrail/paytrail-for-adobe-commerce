<?php

namespace Paytrail\PaymentService\Api\Data;

interface RecurringProfileInterface
{
    const FIELD_PROFILE_ID = 'profile_id';
    const FIELD_NAME = 'name';
    const FIELD_DESCRIPTION = 'description';
    const FIELD_SCHEDULE = 'schedule';

    /**
     * @return int
     */
    public function getId();

    /**
     * @return string
     */
    public function getName();

    /**
     * @return string
     */
    public function getDescription();

    /**
     * @return string
     */
    public function getSchedule();

    /**
     * @param int $profileId
     * @return $this
     */
    public function setId($profileId): self;

    /**
     * @param string $name
     * @return $this
     */
    public function setName($name): self;

    /**
     * @param string $description
     * @return $this
     */
    public function setDescription($description): self;

    /**
     * @param string $schedule
     * @return $this
     */
    public function setSchedule($schedule): self;
}
