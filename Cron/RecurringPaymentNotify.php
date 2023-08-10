<?php

namespace Paytrail\PaymentService\Cron;

use Paytrail\PaymentService\Model\Recurring\Notify;

class RecurringPaymentNotify
{
    /**
     * Constructor
     *
     * @param Notify $notify
     */
    public function __construct(
        private Notify $notify
    ) {
        $this->notify = $notify;
    }

    /**
     * Execute
     *
     * @return void
     */
    public function execute()
    {
        $this->notify->process();
    }
}
