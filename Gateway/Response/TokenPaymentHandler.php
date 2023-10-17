<?php

namespace Paytrail\PaymentService\Gateway\Response;

use Magento\Payment\Gateway\Response\HandlerInterface;

class TokenPaymentHandler implements HandlerInterface
{
    /**
     * @inheritdoc
     *
     * @param array $handlingSubject
     * @param array $response
     * @return mixed|void
     */
    public function handle(array $handlingSubject, array $response)
    {
        return $response['data'];
    }
}
