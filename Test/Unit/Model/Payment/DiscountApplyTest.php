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
            ->onlyMethods([])
            ->getMock();

        $order->setData('gift_cards_amount', 0);
        $order->setData('customer_balance_amount', 0);
        $order->setData('reward_currency_amount', 0);

        $result = $this->discountApply->process($items, $order);

        $this->assertEquals($items, $result);
    }

    public function testProcessWithDiscounts(): void
    {
        $items = ['item1', 'item2'];
        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $order->setData('gift_cards_amount', 50);
        $order->setData('customer_balance_amount', 50);
        $order->setData('reward_currency_amount', 50);

        $result = $this->discountApply->process($items, $order);

        $this->assertNotEquals($items, $result);
        $discountItem = array_pop($result);
        $this->assertContains('Discount', $discountItem);
        $this->assertEquals(-150, (float)$discountItem['price']);
    }

}
