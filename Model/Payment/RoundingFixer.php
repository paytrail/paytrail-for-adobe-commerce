<?php

namespace Paytrail\PaymentService\Model\Payment;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\SalesRule\Model\DeltaPriceRound;

class RoundingFixer
{
    private const CONFIG_PATH_ROUNDING_ROW_TAX   = 'payment/paytrail/rounding_row_tax_percent';

    /**
     * Constructor
     *
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private ScopeConfigInterface $scopeConfig
    ) {
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
     *
     * @throws LocalizedException
     */
    public function correctRoundingErrors(array &$items, float $discountedTotal, float $itemDiscountedTotal): void
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
                'title'  => 'Discount rounding correction',
                'code'   => 'discount-rounding-correction',
                'price'  => $delta,
                'amount' => 1,
                'vat'    => $this->getRoundingRowTax(),
            ];
        }
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

    /**
     * Applies negative delta to a row with the lowest amount of products and returns new delta afterward.
     *
     * @param float $delta
     * @param array $items
     * @param numeric $discountedTotal
     *
     * @return float new delta value after negative delta was embedded
     * @throws LocalizedException
     */
    private function embedNegativeDelta(float $delta, array &$items, $discountedTotal): float
    {
        $lowestAmountIndex = 0;
        foreach ($items as $index => $item) {
            if (($item['price'] * $item['amount']) + $delta < 0) {
                continue;
            }

            $lowestAmountIndex = $items[$lowestAmountIndex]['amount'] > $item['amount'] ? $index : $lowestAmountIndex;
        }

        $deltaToEmbed                       = round($delta / $items[$lowestAmountIndex]['amount'], 2);
        $deltaToEmbed                       = $deltaToEmbed == 0 ? -0.01 : $deltaToEmbed;
        $items[$lowestAmountIndex]['price'] += $deltaToEmbed;

        return round($discountedTotal - $this->getItemTotal($items), 2);
    }

    /**
     * Get rounding row tax
     *
     * @return float
     */
    private function getRoundingRowTax(): float
    {
        return (float)$this->scopeConfig->getValue(
            self::CONFIG_PATH_ROUNDING_ROW_TAX,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
}
