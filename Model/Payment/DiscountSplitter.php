<?php

namespace Paytrail\PaymentService\Model\Payment;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;

class DiscountSplitter
{
    const ROUNDING_TYPE_FINAL = 'splitter_unit';
    const SMALLEST_ACCEPTABLE_DIFFERENCE = 0.0001;
    const CONFIG_PATH_ROUNDING_ROW_TAX = 'payment/paytrail/rounding_row_tax_percent';

    /**
     * @var DiscountGetterInterface[]
     */
    private $discountGetters;
    /**
     * @var \Magento\SalesRule\Model\DeltaPriceRound
     */
    private $deltaPriceRound;
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    public function __construct(
        \Magento\SalesRule\Model\DeltaPriceRound $deltaPriceRound,
        ScopeConfigInterface $scopeConfig,
                                                 $discountGetters = []
    ) {
        $this->deltaPriceRound = $deltaPriceRound;
        $this->discountGetters = $discountGetters;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Splits any cart or order discounts between all items inside the order. And adds rounding correction row, if
     * discounts could not be split evenly.
     *
     * @param array $items
     * @param \Magento\Sales\Model\Order $order
     * @return array
     * @throws LocalizedException
     */
    public function process(array $items, \Magento\Sales\Model\Order $order)
    {
        $discountTotal = $this->getDiscountsFromOrder($order);
        if (!$discountTotal) {
            return $items;
        }

        $itemTotal = $this->getItemTotal($items);
        $discountedItemTotal = 0;
        foreach ($items as $index => $item) {
            $percentage = $this->calculateItemPercentageOfTotal($itemTotal, $item);
            $productDiscount = $discountTotal * $percentage;
            $items[$index]['price'] = $this->deltaPriceRound->round(
                $item['price'] - $productDiscount / $item['amount'],
                self::ROUNDING_TYPE_FINAL
            );

            $discountedItemTotal += $items[$index]['price'] * $items[$index]['amount'];
        }

        $this->correctRoundingErrors($items, $order->getGrandTotal(), $discountedItemTotal);
        $finalTotal = $this->getItemTotal($items);
        if (abs(($order->getGrandTotal() - $finalTotal) / $finalTotal) > self::SMALLEST_ACCEPTABLE_DIFFERENCE) {
            throw new LocalizedException(__(
                'Order discount error: unable to split discount between products'
            ));
        }

        return $items;
    }

    /**
     * Adjust item prices so that combined total from item discounts matches the expected total using only positive
     * values for prices and quantities.
     *
     * Service portal officially supports only positive amounts & prices. Because of this any rounding
     * correction row in the order must contain a positive price and amount. If this practice is not followed. Certain
     * Payment methods can break.
     *
     * @param array $items
     * @param float $discountedTotal
     * @param float $itemDiscountedTotal
     */
    private function correctRoundingErrors(array &$items, float $discountedTotal, float $itemDiscountedTotal)
    {
        $delta = round($discountedTotal - $itemDiscountedTotal, 2);
        if ($delta == 0) {
            return;
        }

        if ($delta < 0) {
            $delta = $this->embedNegativeDelta($delta, $items, $discountedTotal);
        }

        if ($delta > 0) {
            $items[] = [
                'title' => 'Discount rounding correction',
                'code' => 'discount-rounding-correction',
                'price' => $delta,
                'amount' => 1,
                'vat' => $this->getRoundingRowTax(),
            ];
        }
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @return float
     */
    private function getDiscountsFromOrder(\Magento\Sales\Model\Order $order)
    {
        $discountTotal = 0;
        $discountTotal += $order->getGiftCardsAmount();
        $discountTotal += $order->getCustomerBalanceAmount();
        $discountTotal += $order->getRewardCurrencyAmount();

        /** @var \Paytrail\PaymentService\Model\Payment\DiscountGetterInterface $discountGetter */
        foreach ($this->discountGetters as $discountGetter) {
            if (!$discountGetter instanceof \Paytrail\PaymentService\Model\Payment\DiscountGetterInterface) {
                throw new LocalizedException(__(
                    'Discount getters must implement %interface!',
                    ['interface' => \Paytrail\PaymentService\Model\Payment\DiscountGetterInterface::class]
                ));
            }

            $discountTotal += $discountGetter->getDiscount($order);
        }

        return $discountTotal;
    }

    /**
     * @param float $total
     * @param array $item
     * @return float
     */
    private function calculateItemPercentageOfTotal(float $total, array $item)
    {
        $price = $item['price'] * $item['amount'];

        return floatval($price / $total);
    }

    /**
     * @param array $items
     * @return float
     * @throws LocalizedException
     */
    private function getItemTotal(array $items)
    {
        $total = 0;
        foreach ($items as $item) {
            $total += $item['price'] * $item['amount'];
        }

        if (!$total) {
            throw new LocalizedException(\__(
                'Item total should not be 0'
            ));
        }

        return $total;
    }

    /**
     * Applies negative delta to a row with the lowest amount of products and returns new delta afterwards.
     *
     * @param float $delta
     * @param array $items
     * @return float new delta value after negative delta was embedded
     */
    private function embedNegativeDelta(float $delta, array &$items, $discountedTotal)
    {
        $lowestAmountIndex = 0;
        foreach ($items as $index => $item) {
            if (($item['price'] * $item['amount']) + $delta < 0) {
                continue;
            }

            $lowestAmountIndex = $items[$lowestAmountIndex]['amount'] > $item['amount'] ? $index : $lowestAmountIndex;
        }

        $deltaToEmbed = round($delta / $items[$lowestAmountIndex]['amount'], 2);
        $deltaToEmbed = $deltaToEmbed == 0 ? -0.01 : $deltaToEmbed;
        $items[$lowestAmountIndex]['price'] += $deltaToEmbed;

        return round($discountedTotal - $this->getItemTotal($items), 2);
    }

    /**
     * @return int
     */
    private function getRoundingRowTax() : float
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_PATH_ROUNDING_ROW_TAX,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
}
