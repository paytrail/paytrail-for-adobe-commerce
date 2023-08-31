<?php

namespace Paytrail\PaymentService\Cron;

use Paytrail\PaymentService\Model\Recurring\TotalConfigProvider;

class RecurringPaymentBill
{
    /** @var \Paytrail\PaymentService\Model\Recurring\Bill */
    private $bill;

    /**
     * @var TotalConfigProvider
     */
    private $totalConfigProvider;

    /**
     * @param \Paytrail\PaymentService\Model\Recurring\Bill $bill
     * @param TotalConfigProvider $totalConfigProvider
     */
    public function __construct(
        \Paytrail\PaymentService\Model\Recurring\Bill $bill,
        TotalConfigProvider $totalConfigProvider
    ) {
        $this->bill = $bill;
        $this->totalConfigProvider = $totalConfigProvider;
    }

    /**
     * Execute.
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        if ($this->totalConfigProvider->isRecurringPaymentEnabled()) {
            $this->bill->process();
        }
    }
}
