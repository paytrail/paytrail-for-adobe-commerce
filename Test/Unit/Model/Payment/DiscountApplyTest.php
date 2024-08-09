<?php

namespace Tests\Unit\PaymentService\Model\Payment;

use PHPUnit\Framework\TestCase;
use Paytrail\PaymentService\Model\Payment\DiscountApply;
use Magento\Sales\Model\Order;
use Paytrail\PaymentService\Model\Payment\DiscountGetterInterface;

class DiscountApplyTest extends TestCase
{
    /**
     * @var DiscountApply
     */
    private $discountApply;

    protected function setUp(): void
    {
        $this->discountApply = new DiscountApply();
    }

    public function testProcessWithoutDiscounts(): void
    {
        $items = ['item1', 'item2'];
        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->addMethods(['getGiftCardsAmount', 'getCustomerBalanceAmount', 'getRewardCurrencyAmount'])
            ->onlyMethods(['getShippingAmount', 'getGrandTotal', 'getShippingTaxAmount', 'getAllItems'])
            ->getMock();

        $order->method('getGiftCardsAmount')->willReturn(0);
        $order->method('getCustomerBalanceAmount')->willReturn(0);
        $order->method('getRewardCurrencyAmount')->willReturn(0);

        $result = $this->discountApply->process($items, $order);

        $this->assertEquals($items, $result);
    }

    public function testProcessWithDiscounts(): void
    {
        $items = ['item1', 'item2'];
        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->addMethods(['getGiftCardsAmount', 'getCustomerBalanceAmount', 'getRewardCurrencyAmount'])
            ->onlyMethods(['getShippingAmount', 'getGrandTotal', 'getShippingTaxAmount', 'getAllItems'])
            ->getMock();

        $order->method('getGiftCardsAmount')->willReturn(50);
        $order->method('getCustomerBalanceAmount')->willReturn(50);
        $order->method('getRewardCurrencyAmount')->willReturn(50);

        $result = $this->discountApply->process($items, $order);

        $this->assertNotEquals($items, $result);
        $discountItem = array_pop($result);
        $this->assertContains('Discount', $discountItem);
        $this->assertEquals(-150, (float)$discountItem['price']);
    }

    // Add more tests here...
}
