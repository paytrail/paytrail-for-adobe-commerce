<?php

namespace Paytrail\PaymentService\Console\Command;

use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Paytrail\PaymentService\Model\Recurring\Notify as RecurringNotify;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Notify extends Command
{
    /**
     * Constructor
     *
     * @param RecurringNotify $notify
     * @param State $state
     */
    public function __construct(
        private RecurringNotify $notify,
        private State           $state
    ) {
        parent::__construct();
    }

    /**
     * Configure
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('paytrail:recurring:notify');
        $this->setDescription('Send recurring payment notification emails.');
    }

    /**
     * Execute
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_CRONTAB);
        $this->notify->process();

        return Cli::RETURN_SUCCESS;
    }
}
