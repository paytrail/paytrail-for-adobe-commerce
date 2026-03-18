<?php
declare(strict_types=1);

namespace Paytrail\PaymentService\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Payment\Gateway\Command\CommandManagerPoolInterface;
use Magento\Sales\Model\Order;
use Paytrail\PaymentService\Model\PaymentMethod\OrderPaymentMethodData;
use Psr\Log\LoggerInterface;

class OrderInvoiceCancellation implements ObserverInterface
{
    /**
     * OrderInvoiceCancellation constructor.
     *
     * @param CommandManagerPoolInterface $commandManagerPool
     * @param LoggerInterface $logger
     */
    public function __construct(
        private CommandManagerPoolInterface $commandManagerPool,
        private LoggerInterface             $logger
    ) {
    }

    /**
     * Execute observer on order_cancel_after event.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        try {
            $order = $observer->getEvent()->getOrder();
            $paymentMethod = $order
                ->getPayment()
                ->getAdditionalInformation()[OrderPaymentMethodData::SELECTED_PAYMENT_METHOD_CODE];

            if ($order->getState() === Order::STATE_CANCELED && $paymentMethod === 'klarna') {
                $commandExecutor = $this->commandManagerPool->get('paytrail');

                $response = $commandExecutor->executeByCode(
                    'invoice_cancellation',
                    null,
                    [
                        'transaction_id' => $order->getPayment()->getLastTransId()
                    ]
                );

                if (!$response['error']) {
                    $order->getPayment()->addTransactionCommentsToOrder(
                        $order->getPayment()->getTransactionId(),
                        __(
                            'Invoice cancellation successfully with code: "%code".',
                            ['code' => $response['data']->getHttpStatusCode()]
                        )
                    );
                }
            }
        } catch (\Exception $e) {
            $this->logger->error(
                'Error occurred during invoice cancellation: '
                . $e->getMessage()
            );
        }
    }
}
