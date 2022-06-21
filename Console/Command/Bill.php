<?php

namespace Paytrail\PaymentService\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Bill extends \Symfony\Component\Console\Command\Command
{
    /** @var \Paytrail\PaymentService\Model\Recurring\Bill */
    private $bill;

    /** @var \Magento\Framework\App\State */
    private $state;

    public function __construct(
        \Paytrail\PaymentService\Model\Recurring\Bill\Proxy $notify,
        \Magento\Framework\App\State $state
    ) {
        $this->bill = $notify;
        $this->state = $state;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('paytrail:recurring:bill');
        $this->setDescription('Invoice customers of recurring orders');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_CRONTAB);
        $this->bill->process();
    }
}
