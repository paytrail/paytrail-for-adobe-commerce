<?php

namespace Paytrail\PaymentService\Observer\Email\Order;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Psr\Log\LoggerInterface;

class PendingOrderEmailConfirmation implements ObserverInterface
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
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        try {
            if ($this->isPendingOrderEmailEnabled()) {
                $this->orderSender->send($observer->getEvent()->getOrder());
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
