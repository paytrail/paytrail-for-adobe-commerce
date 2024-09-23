<?php

namespace Paytrail\PaymentService\Model\Order;

use Magento\Sales\Model\Order\Item;
use Paytrail\PaymentService\Gateway\Config\Config;

class OrderDataAnonymization
{
    private const ANONYMIZED_DATA = '*****';

    /**
     * OrderAnonymization constructor.
     *
     * @param Config $gatewayConfig
     */
    public function __construct(
        private readonly Config $gatewayConfig
    ) {
    }

    /**
     * Anonymization of the items in the order.
     *
     * @param $orderItem
     * @return Item
     */
    public function anonymizeItemData($orderItem): Item
    {
        if ($orderItem->getProductType() === $this->gatewayConfig->getAnonymizationProductType()) {
            $orderItem->setName(self::ANONYMIZED_DATA);
            $orderItem->setSku(self::ANONYMIZED_DATA);
        }

        return $orderItem;
    }
}
