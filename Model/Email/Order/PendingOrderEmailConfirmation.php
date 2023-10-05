<?php

namespace Paytrail\PaymentService\Model\Email\Order;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Psr\Log\LoggerInterface;

class PendingOrderEmailConfirmation
{
    public const PENDING_ORDER_EMAIL_PATH = 'payment/paytrail/order_place_redirect_url';

    private ScopeConfigInterface $scopeConfig;

    private OrderSender $orderSender;

    private LoggerInterface $logger;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param OrderSender $orderSender
     * @param LoggerInterface $logger
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        OrderSender          $orderSender,
        LoggerInterface      $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->orderSender = $orderSender;
        $this->logger = $logger;
    }

    /**
     * @param $order
     * @return void
     */
    public function pendingOrderEmailSend($order)
    {
        try {
            if ($this->isPendingOrderEmailEnabled()) {
                $this->orderSender->send($order);
            }
        } catch (\Exception $e) {
            $this->logger->error(\sprintf(
                'Paytrail: Order email sending failed: %s',
                $e->getMessage()
            ));
        }
    }

    /**
     * @return bool
     */
    public function isPendingOrderEmailEnabled(): bool
    {
        return $this->scopeConfig->getValue(self::PENDING_ORDER_EMAIL_PATH);
    }
}
