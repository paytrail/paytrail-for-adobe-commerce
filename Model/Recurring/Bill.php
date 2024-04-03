<?php

namespace Paytrail\PaymentService\Model\Recurring;

use Paytrail\PaymentService\Model\Subscription\ActiveOrderProvider;
use Paytrail\PaymentService\Model\Subscription\OrderBiller;
use Paytrail\PaymentService\Model\ResourceModel\Subscription;

class Bill
{
    /**
     * @var OrderBiller
     */
    private $orderBiller;

    /**
     * @var ActiveOrderProvider
     */
    private $activeOrders;

    /**
     * @param OrderBiller $orderBiller
     * @param ActiveOrderProvider $activeOrderProvider
     */
    public function __construct(
        OrderBiller $orderBiller,
        ActiveOrderProvider $activeOrderProvider
    ) {
        $this->orderBiller = $orderBiller;
        $this->activeOrders = $activeOrderProvider;
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function process()
    {
        $validOrders = $this->getValidOrderIds();

        if (empty($validOrders)) {
            return;
        }
        $this->orderBiller->billOrdersById($validOrders);
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getValidOrderIds()
    {
        return $this->activeOrders->getPayableOrderIds();
    }
}
