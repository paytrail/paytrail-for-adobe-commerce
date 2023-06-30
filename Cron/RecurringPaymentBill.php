<?php

namespace Paytrail\PaymentService\Cron;

class RecurringPaymentBill
{
    /** @var \Paytrail\PaymentService\Model\Recurring\Bill */
    private $bill;

    public function __construct(
        \Paytrail\PaymentService\Model\Recurring\Bill $bill
    ) {
        $this->bill = $bill;
    }

    public function execute()
    {
        $this->bill->process();
    }
}
