<?php

namespace Paytrail\PaymentService\Gateway\Response;

use Magento\Framework\Message\ManagerInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;

class PaymentHandler implements HandlerInterface
{
    /**
     * RefundHandler constructor.
     *
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        private ManagerInterface $messageManager,
    ) {
    }

    /**
     * @inheritdoc
     *
     * @param array $handlingSubject
     * @param array $response
     * @return mixed|void
     */
    public function handle(array $handlingSubject, array $response)
    {
        $this->messageManager->addSuccessMessage(__('Paytrail payment is proceeding.'));
        return $response['data'];
    }
}
