<?php

namespace Paytrail\PaymentService\Gateway\Response;

use Magento\Framework\Message\ManagerInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;

class PaymentMethodProviderHandler implements HandlerInterface
{
    /**
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        private readonly ManagerInterface $messageManager,
    ) {
    }

    /**
     * @param array $handlingSubject
     * @param array $response
     * @return mixed|void
     */
    public function handle(array $handlingSubject, array $response)
    {
        $this->messageManager->addSuccessMessage(__('Paytrail payment method provider is proceeding.'));
        return $response['data'];
    }
}
