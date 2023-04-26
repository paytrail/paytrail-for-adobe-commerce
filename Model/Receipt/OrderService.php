<?php

namespace Paytrail\PaymentService\Model\Receipt;

use Magento\Framework\App\CacheInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\OrderFactory;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Helper\Data as PaytrailHelper;
use Paytrail\PaymentService\Setup\Patch\Data\InstallPaytrail;
use Psr\Log\LoggerInterface;

class OrderService
{
    public const RECEIPT_PROCESSING_CACHE_PREFIX = "receipt_processing_";

    /**
     * @param OrderSender $orderSender
     * @param OrderRepositoryInterface $orderRepositoryInterface
     * @param CacheInterface $cache
     * @param PaytrailHelper $paytrailHelper
     * @param Config $gatewayConfig
     * @param LoggerInterface $logger
     * @param OrderFactory $orderFactory
     * @param Order $currentOrder
     */
    public function __construct(
        private OrderSender $orderSender,
        private OrderRepositoryInterface $orderRepositoryInterface,
        private CacheInterface $cache,
        private PaytrailHelper $paytrailHelper,
        private Config $gatewayConfig,
        private LoggerInterface $logger,
        private OrderFactory $orderFactory,
        private Order $currentOrder,
    ) {
    }

    /**
     * @param int $orderId
     */
    public function lockProcessingOrder($orderId)
    {
        /** @var string $identifier */
        $identifier = self::RECEIPT_PROCESSING_CACHE_PREFIX . $orderId;

        $this->cache->save("locked", $identifier);
    }

    /**
     * @param int $orderId
     */
    public function unlockProcessingOrder($orderId)
    {
        /** @var string $identifier */
        $identifier = self::RECEIPT_PROCESSING_CACHE_PREFIX . $orderId;

        $this->cache->remove($identifier);
    }

    /**
     * @param int $orderId
     * @return bool
     */
    public function isOrderLocked($orderId)
    {
        /** @var string $identifier */
        $identifier = self::RECEIPT_PROCESSING_CACHE_PREFIX . $orderId;

        return $this->cache->load($identifier) ? true : false;
    }

    /**
     * @param $paymentVerified
     * @param $currentOrder
     * @return void
     */
    public function processOrder($paymentVerified, $currentOrder)
    {
        $orderState = $this->gatewayConfig->getDefaultOrderStatus();

        if ($paymentVerified === 'ok') {
            $currentOrder->setState($orderState)->setStatus($orderState);
            $currentOrder->addCommentToStatusHistory(__('Payment has been completed'));
        } else {
            $currentOrder->setState(InstallPaytrail::ORDER_STATE_CUSTOM_CODE);
            $currentOrder->setStatus(InstallPaytrail::ORDER_STATUS_CUSTOM_CODE);
            $currentOrder->addCommentToStatusHistory(__('Pending payment from Paytrail Payment Service'));
        }

        $this->orderRepositoryInterface->save($this->currentOrder);

        try {
            $this->orderSender->send($currentOrder);
        } catch (\Exception $e) {
            $this->logger->error(\sprintf(
                'Paytrail: Order email sending failed: %s',
                $e->getMessage()
            ));
        }
    }

    /**
     * @param $orderIncrementalId
     * @return Order
     * @throws CheckoutException
     */
    public function loadOrder($orderIncrementalId)
    {
        $order = $this->orderFactory->create()->loadByIncrementId($orderIncrementalId);
        if (!$order->getId()) {
            $this->paytrailHelper->processError('Order not found');
        }
        return $order;
    }

}
