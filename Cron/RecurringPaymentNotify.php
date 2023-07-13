<?php

namespace Paytrail\PaymentService\Cron;

class RecurringPaymentNotify
{
    /** @var \Paytrail\PaymentService\Model\Recurring\Notify */
    private $notify;

    public function __construct(
        \Paytrail\PaymentService\Model\Recurring\Notify $notify
    ) {
        $this->notify = $notify;
    }

    public function execute()
    {
        $this->notify->process();
    }
}
