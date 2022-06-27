<?php

namespace Paytrail\PaymentService\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Notify extends \Symfony\Component\Console\Command\Command
{
    /** @var \Paytrail\PaymentService\Model\Recurring\Notify */
    private $notify;

    /** @var \Magento\Framework\App\State */
    private $state;

    public function __construct(
        \Paytrail\PaymentService\Model\Recurring\Notify $notify,
        \Magento\Framework\App\State $state
    ) {
        $this->notify = $notify;
        $this->state = $state;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('paytrail:recurring:notify');
        $this->setDescription('Send recurring payment notification emails.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_CRONTAB);
        $this->notify->process();
    }
}
