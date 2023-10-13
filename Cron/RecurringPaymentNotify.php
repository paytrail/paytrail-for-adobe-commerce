<?php

namespace Paytrail\PaymentService\Cron;

use Paytrail\PaymentService\Model\Recurring\Notify;
use Paytrail\PaymentService\Model\Recurring\TotalConfigProvider;

class RecurringPaymentNotify
{
    /**
     * RecurringPaymentNotify constructor.
     *
     * @param Notify $notify
     * @param TotalConfigProvider $totalConfigProvider
     */
    public function __construct(
        private Notify $notify,
        private TotalConfigProvider $totalConfigProvider
    ) {
    }

    /**
     * Execute
     *
     * @return void
     */
    public function execute()
    {
        if ($this->totalConfigProvider->isRecurringPaymentEnabled()) {
            $this->notify->process();
        }
    }
}
