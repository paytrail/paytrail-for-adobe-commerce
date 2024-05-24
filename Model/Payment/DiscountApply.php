<?php

namespace Paytrail\PaymentService\Model\Payment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;

class DiscountApply
{
    /**
     * Constructor
     *
     * @param array $discountGetters
     */
    public function __construct(
        private array $discountGetters = []
    ) {
    }

    /**
     * Process function
     *
     * @param array $items
     * @param Order $order
     *
     * @return array
     * @throws LocalizedException
     */
    public function process(array $items, Order $order): array
    {
        $discountTotal = $this->getDiscountsFromOrder($order);
        if (!$discountTotal) {
            return $items;
        }

        $items[] = [
            'title' => 'Discount',
            'code' => 'discount',
            'price' => -$discountTotal,
            'amount' => 1,
            'vat' => 0,
        ];

        return $items;
    }

    /**
     * Get discounts from order
     *
     * @param Order $order
     *
     * @return float|null
     * @throws LocalizedException
     */
    public function getDiscountsFromOrder(Order $order): float|null
    {
        $discountTotal = 0;
        $discountTotal += $order->getGiftCardsAmount();
        $discountTotal += $order->getCustomerBalanceAmount();
        $discountTotal += $order->getRewardCurrencyAmount();

        /** @var DiscountGetterInterface $discountGetter */
        foreach ($this->discountGetters as $discountGetter) {
            if (!$discountGetter instanceof DiscountGetterInterface) {
                throw new LocalizedException(
                    __(
                        'Discount getters must implement %interface!',
                        ['interface' => DiscountGetterInterface::class]
                    )
                );
            }

            $discountTotal += $discountGetter->getDiscount($order);
        }

        return $discountTotal;
    }
}
