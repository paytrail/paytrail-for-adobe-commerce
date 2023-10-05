<?php

namespace Paytrail\PaymentService\Model\Subscription;

use Paytrail\PaymentService\Api\Data\RecurringProfileInterface;

class Profile extends \Magento\Framework\Model\AbstractModel implements RecurringProfileInterface
{
    protected function _construct()
    {
        $this->_init(\Paytrail\PaymentService\Model\ResourceModel\Subscription\Profile::class);
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->getData('profile_id');
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->getData('name');
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->getData('description');
    }

    /**
     * @return string
     */
    public function getSchedule()
    {
        return $this->getData('schedule');
    }

    public function setId($profileId): self
    {
        return $this->setData('profile_id', $profileId);
    }

    public function setName($name): self
    {
        return $this->setData('name', $name);
    }

    public function setDescription($description): self
    {
        return $this->setData('description', $description);
    }

    public function setSchedule($schedule): self
    {
        return $this->setData('schedule', $schedule);
    }
}
