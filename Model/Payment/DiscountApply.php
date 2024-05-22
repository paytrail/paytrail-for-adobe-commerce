<?php

namespace Paytrail\PaymentService\Model\Payment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\SalesRule\Model\DeltaPriceRound;

class DiscountApply
{
    private const ROUNDING_TYPE_FINAL            = 'splitter_unit';
    private const SMALLEST_ACCEPTABLE_DIFFERENCE = 0.0001;

    /**
     * Constructor
     *
     * @param DeltaPriceRound $deltaPriceRound
     * @param RoundingFixer $roundingFixer
     * @param array $discountGetters
     */
    public function __construct(
        private DeltaPriceRound $deltaPriceRound,
        private RoundingFixer   $roundingFixer,
        private                 $discountGetters = []
    ) {
    }

    /**
     * Splits any cart or order discounts between all items inside the order. Adds rounding correction row if needed.
     *
     * @param array $items
     * @param Order $order
     *
     * @return array
     * @throws LocalizedException
     */
    public function process(array $items, Order $order): array
    {
        list($discountTotal, $discounts) = $this->getDiscountsFromOrder($order);
        if (!$discountTotal) {
            //fix for rounding errors
            $this->roundingFixer->correctRoundingErrors($items, $order->getGrandTotal(), $this->getItemTotal($items));
            return $items;
        }

       foreach ($discounts as $discount) {
           $items[] = $discount;
       }

        $finalTotal = $this->getItemTotal($items);

        return $items;
    }

    /**
     * @param Order $order
     *
     * @return float|int
     * @throws LocalizedException
     */
    public function getDiscountsFromOrder(Order $order)
    {
        $discounts = [];
        $discountTotal = 0;
        if ($order->getGiftCardsAmount()) {
            $discountTotal += $order->getGiftCardsAmount();
            $discounts[] = [
                'title'  => 'Gift Card',
                'code'   => 'gift-card',
                'price'  => -$order->getGiftCardsAmount(),
                'amount' => 1,
                'vat'    => 0,
            ];
        };


        if ($order->getCustomerBalanceAmount()) {
            $discountTotal += $order->getCustomerBalanceAmount();
            $discounts[] = [
                'title'  => 'Store Credit',
                'code'   => 'store-credit',
                'price'  => -$order->getCustomerBalanceAmount(),
                'amount' => 1,
                'vat'    => 0,
            ];
        }

        if ($order->getRewardCurrencyAmount()) {
            $discountTotal += $order->getRewardCurrencyAmount();
            $discounts[] = [
                'title'  => 'Reward Points',
                'code'   => 'reward-points',
                'price'  => -$order->getRewardCurrencyAmount(),
                'amount' => 1,
                'vat'    => 0,
            ];
       }

        /** @var \Paytrail\PaymentService\Model\Payment\DiscountGetterInterface $discountGetter */
        foreach ($this->discountGetters as $discountGetter) {
            if (!$discountGetter instanceof \Paytrail\PaymentService\Model\Payment\DiscountGetterInterface) {
                throw new LocalizedException(
                    __(
                        'Discount getters must implement %interface!',
                        ['interface' => \Paytrail\PaymentService\Model\Payment\DiscountGetterInterface::class]
                    )
                );
            }

            $discountTotal += $discountGetter->getDiscount($order);
        }

        return [$discountTotal, $discounts];
    }

    /**
     * Calculate item percentage of total
     *
     * @param float $total
     * @param array $item
     *
     * @return float
     */
    private function calculateItemPercentageOfTotal(float $total, array $item)
    {
        $price = $item['price'] * $item['amount'];

        return floatval($price / $total);
    }

    /**
     * Get item total
     *
     * @param array $items
     *
     * @return float|int
     * @throws LocalizedException
     */
    private function getItemTotal(array $items)
    {
        $total = 0;
        foreach ($items as $item) {
            $total += $item['price'] * $item['amount'];
        }

        if (!$total) {
            throw new LocalizedException(\__('Item total should not be 0'));
        }

        return $total;
    }
}
