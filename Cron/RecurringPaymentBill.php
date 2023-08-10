<?php

namespace Paytrail\PaymentService\Cron;

use Paytrail\PaymentService\Model\Recurring\Bill;

class RecurringPaymentBill
{
    /**
     * Constructor
     *
     * @param Bill $bill
     */
    public function __construct(
        private Bill $bill
    ) {
    }

    /**
     * Execute
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        $this->bill->process();
    }
}
