<?php
/*
 *  Copyright © 2024 GoodAhead. All rights reserved.
 */
declare(strict_types=1);

namespace Goodahead\Paytrail\Plugin;

use Magento\Sales\Model\Order;
use Paytrail\PaymentService\Model\Receipt\ProcessService;
use Psr\Log\LoggerInterface;

class ProcessServicePlugin
{
    /**
     * @param LoggerInterface $logger
     */
    public function __construct(
        protected LoggerInterface $logger
    ) {}

    /**
     * @param ProcessService $subject
     * @param Order $currentOrder
     * @param string $transactionId
     * @param array $details
     * @return array
     */
    public function beforeProcessPayment(ProcessService $subject, $currentOrder, $transactionId, $details): array
    {
        if ($currentOrder->getStatus() == 'canceled') {
            $this->logger->warning(
                sprintf(
                    'Paytrail processing payment for order %s(%s) failed. Transaction ID - %s:',
                    $currentOrder->getId(),
                    $currentOrder->getIncrementId(),
                    $transactionId
                )
            );
            $this->logger->warning(
                sprintf(
                    'Details: %s',
                    json_encode($details)
                )
            );
        }

        return [$currentOrder, $transactionId, $details];
    }
}
