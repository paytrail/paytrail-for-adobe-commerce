<?php

namespace Paytrail\PaymentService\Model\Order;

use Magento\Sales\Model\Order\Item;
use Paytrail\PaymentService\Gateway\Config\Config;

class OrderDataAnonymization
{
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
            $orderItem->setName('*****');
            $orderItem->setSku('*****');
        }

        return $orderItem;
    }
}
