<?php

namespace Paytrail\PaymentService\Model\Subscription;

use Carbon\Carbon;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;

class NextDateCalculator
{
    /**
     * @var \Paytrail\PaymentService\Api\RecurringProfileRepositoryInterface $profileRepo
     */
    private $profileRepo;
    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var \Paytrail\PaymentService\Api\Data\RecurringProfileInterface[]
     */
    private $profiles = [];
    private ScopeConfigInterface $scopeConfig;

    public function __construct(
        \Paytrail\PaymentService\Api\RecurringProfileRepositoryInterface $profileRepository,
        SerializerInterface                                  $serializer,
        ScopeConfigInterface                                 $scopeConfig
    ) {
        $this->profileRepo = $profileRepository;
        $this->serializer = $serializer;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param $profileId
     * @param string $startDate
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getNextDate($profileId, $startDate = 'now')
    {
        $profile = $this->getProfileById($profileId);

        return $this->calculateNextDate($profile->getSchedule(), $startDate);
    }

    /**
     * @param $schedule
     * @param $startDate
     * @return string
     * @throws \Exception
     */
    protected function calculateNextDate($schedule, $startDate)
    {
        $schedule = $this->serializer->unserialize($schedule);
        $carbonDate = $startDate === 'now' ? Carbon::now() : Carbon::createFromFormat('Y-m-d H:i:s', $startDate);

        switch ($schedule['unit']) {
            case 'D':
                $nextDate = $carbonDate->addDays($schedule['interval']);
                break;
            case 'W':
                $nextDate = $carbonDate->addWeeks($schedule['interval']);
                break;
            case 'M':
                $nextDate = $this->addMonthsNoOverflow($carbonDate, $schedule['interval']);
                break;
            case 'Y':
                $nextDate = $carbonDate->addYearsNoOverflow($schedule['interval']);
                break;
            default:
                throw new LocalizedException(__('Schedule type not supported'));
        }

        if ($this->isForceWeekdays()) {
            $nextDate = $this->getNextWeekday($nextDate);
        }

        return $nextDate->format('Y-m-d');
    }

    /**
     * @param $profileId
     * @return \Paytrail\PaymentService\Api\Data\RecurringProfileInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function getProfileById($profileId): \Paytrail\PaymentService\Api\Data\RecurringProfileInterface
    {
        if (!isset($this->profiles[$profileId])) {
            $this->profiles[$profileId] = $this->profileRepo->get($profileId);
        }

        return $this->profiles[$profileId];
    }

    /**
     * @return false
     */
    protected function isForceWeekdays()
    {
        if (!isset($this->forceWeekdays)) {
            $this->forceWeekdays = $this->scopeConfig->isSetFlag('sales/recurring_payment/force_weekdays');
        }
        return $this->forceWeekdays;
    }

    /**
     * @param $nextDate
     * @return Carbon
     */
    private function getNextWeekday($nextDate)
    {
        $newCarbonDate = new Carbon($nextDate);
        if (!$newCarbonDate->isWeekday()) {
            $newCarbonDate = $newCarbonDate->nextWeekday();
            if ($nextDate->format('m') != $newCarbonDate->format('m')) {
                $newCarbonDate = $newCarbonDate->previousWeekday();
            }
        }
        return $newCarbonDate;
    }

    /**
     * @param \Carbon\Carbon $carbonDate
     * @param int $interval
     * @return \Carbon\Carbon
     */
    protected function addMonthsNoOverflow($carbonDate, $interval)
    {
        $isLastOfMonth = $carbonDate->isLastOfMonth();
        $nextDate = $carbonDate->addMonthsNoOverflow($interval);

        // adjust date to match the last day of month if the previous date was also last date of month.
        if ($isLastOfMonth) {
            $nextDate->endOfMonth();
        }

        return $nextDate;
    }
}
