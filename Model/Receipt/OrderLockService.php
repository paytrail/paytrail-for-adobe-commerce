<?php

namespace Paytrail\PaymentService\Model\Receipt;

use Magento\Framework\App\CacheInterface;

class OrderLockService
{
    private const RECEIPT_PROCESSING_CACHE_PREFIX = "receipt_processing_";

    /**
     * @param CacheInterface $cache
     */
    public function __construct(
        private CacheInterface $cache
    ) {
    }

    /**
     * LockProcessingOrder function
     *
     * @param $orderId
     * @return void
     */
    public function lockProcessingOrder($orderId)
    {
        /** @var string $identifier */
        $identifier = self::RECEIPT_PROCESSING_CACHE_PREFIX . $orderId;

        $this->cache->save("locked", $identifier);
    }

    /**
     * UnlockProcessingOrder function
     *
     * @param $orderId
     * @return void
     */
    public function unlockProcessingOrder($orderId)
    {
        /** @var string $identifier */
        $identifier = self::RECEIPT_PROCESSING_CACHE_PREFIX . $orderId;

        $this->cache->remove($identifier);
    }

    /**
     * IsOrderLocked function
     *
     * @param $orderId
     * @return bool
     */
    public function isOrderLocked($orderId)
    {
        /** @var string $identifier */
        $identifier = self::RECEIPT_PROCESSING_CACHE_PREFIX . $orderId;

        return $this->cache->load($identifier) ? true : false;
    }
}
