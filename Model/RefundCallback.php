<?php

namespace Paytrail\PaymentService\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Paytrail\SDK\Model\CallbackUrl;

/**
 * Class RefundCallback
 */
class RefundCallback
{
    /**
     * @param UrlInterface $urlBuilder
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private UrlInterface          $urlBuilder,
        private StoreManagerInterface $storeManager
    )
    {
    }

    /**
     * @return CallbackUrl
     */
    public function createRefundCallback()
    {
        $callback = new CallbackUrl();

        try {
            $storeUrl = $this->storeManager
                ->getStore()
                ->getBaseUrl(UrlInterface::URL_TYPE_WEB, true);
        } catch (NoSuchEntityException $e) {
            $storeUrl = $this->urlBuilder->getBaseUrl();
        }

        $callback->setSuccess($storeUrl);
        $callback->setCancel($storeUrl);

        return $callback;
    }
}
