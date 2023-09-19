<?php

namespace Paytrail\PaymentService\Cron;

use Paytrail\PaymentService\Model\Recurring\TotalConfigProvider;

class RecurringPaymentNotify
{
    /** @var \Paytrail\PaymentService\Model\Recurring\Notify */
    private $notify;

    /**
     * @var TotalConfigProvider
     */
    private $totalConfigProvider;

    public function __construct(
        \Paytrail\PaymentService\Model\Recurring\Notify $notify,
        TotalConfigProvider $totalConfigProvider
    ) {
        $this->notify = $notify;
        $this->totalConfigProvider = $totalConfigProvider;
    }

    public function execute()
    {
        if ($this->totalConfigProvider->isRecurringPaymentEnabled()) {
            $this->notify->process();
        }
    }
}
