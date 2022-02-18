<?php

namespace Paytrail\PaymentService\Model\Payment;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\SalesRule\Model\DeltaPriceRound;
use PHPUnit\Framework\TestCase;

class DiscountSplitterTest extends TestCase
{
    /**
     * @var DiscountSplitter
     */
    private $discountSplitter;

    protected function setUp(): void
    {
        $priceCurrency = $this->getMockForAbstractClass(PriceCurrencyInterface::class);
        $priceCurrency->method('round')
            ->willReturnCallback(
                function ($amount) {
                    return round($amount, 2);
                }
            );
        $configMock = $this->getMockForAbstractClass(ScopeConfigInterface::class);
        $configMock->method('getValue')->willReturn(24);

        /** @var \Paytrail\PaymentService\Model\Payment\DiscountSplitter $discountSplitter */
        $this->discountSplitter = new DiscountSplitter(
            new DeltaPriceRound($priceCurrency),
            $configMock
        );
    }

    /**
     * @dataProvider processDataProvider
     */
    /**
     * @dataProvider processDataProvider
     */
    public function testProcess($items, $discounts, $expected)
    {
        $items = $this->mockItemArray($items);
        $order = $this->createOrderMock($items, $discounts);
        $orderTotalWithoutDiscount = 0;
        $availableDiscount = 0;
        foreach ($items as $item) {
            $orderTotalWithoutDiscount += $item['price'] * $item['amount'];
        }
        foreach ($discounts as $discount) {
            $availableDiscount += $discount;
        }

        $results = $this->discountSplitter->process($items, $order);
        $discountedTotal = 0;

        foreach ($results as $key => $result) {
            if (!isset($expected[$result['code']])) {
                $this->fail(__(
                    '%row with price: %price & amount: %amount was not found in expected results',
                    [
                        'row' => $key,
                        'price' => $result['price'],
                        'amount' => $result['amount']
                    ]
                ));
            }

            $this->assertEquals(
                $expected[$result['code']],
                $result['price'],
                __(
                    'item row: %row price: %price does not match the expect price: %expected',
                    [
                        'row' => $result['code'],
                        'price' => $result['price'],
                        'expected' => $expected[$result['code']]
                    ]
                )
            );
            $discountedTotal += $result['price'] * $result['amount'];
        }

        $this->assertEquals(
            $orderTotalWithoutDiscount - $availableDiscount,
            $discountedTotal,
            'Discounted total does not match expected total'
        );
    }

    private function createOrderMock($items, $discounts)
    {
        $orderMock = $this->getMockBuilder(\Magento\Sales\Model\Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getDiscountAmount',
                'getAllItems',
                'getShippingDiscountAmount',
                'getGrandTotal'
            ])
            ->addMethods([
                'getGiftCardsAmount',
                'getCustomerBalanceAmount',
                'getRewardCurrencyAmount'
            ])->getMock();

        // Do not use get DiscountAmount method for orders in this callstack. It is already spread to order items!
        $orderMock->expects($this->never())->method('getDiscountAmount');
        $grandTotal = 0;
        foreach ($items as $item) {
            $grandTotal += $item['price'] * $item['amount'];
        }

        foreach ($discounts as $discountName => $discountAmount) {
            $grandTotal -= $discountAmount;
            $orderMock->method(sprintf('get%s', $discountName))->willReturn($discountAmount);
        }
        $orderMock->method('getGrandTotal')->willReturn($grandTotal);

        return $orderMock;
    }

    private function mockItemArray($items)
    {
        foreach ($items as $index => $item) {
            $items[$index]['title'] = $item['code'] . '-test';
            $items[$index]['vat'] = 24;
        }

        return $items;
    }

    public function processDataProvider()
    {
        // expectations are calculated using following formula
        // expectation = productPrice - (discountAmount * (productPrice * amount / orderTotal) / productAmount)

        return [
            'no discounts' => [
                'items' => [
                    [
                        'code' => 'testSku',
                        'price' => floatval(30),
                        'amount' => 1
                    ],
                    [
                        'code' => 'shipping',
                        'price' => floatval(5),
                        'amount' => 1
                    ]
                ],
                'discounts' => [],
                'expected' => [
                    'testSku' => floatval(30),
                    'shipping' => floatval(5),
                ]
            ],
            'Product + shipping: 10 € discount' => [
                'items' => [
                    [
                        'code' => 'testSku',
                        'price' => floatval(70),
                        'amount' => 1
                    ],
                    [
                        'code' => 'shipping',
                        'price' => floatval(30),
                        'amount' => 1
                    ]
                ],
                'discounts' => [
                    'giftCardsAmount' => 10
                ],
                'expected' => [
                    'testSku' => floatval(63),
                    'shipping' => floatval(27),
                ]
            ],
            '5€ Product x 3: 10 € discount' => [
                'items' => [
                    [
                        'code' => 'testSku',
                        'price' => floatval(5),
                        'amount' => 3
                    ],
                    [
                        'code' => 'shipping',
                        'price' => floatval(0),
                        'amount' => 1
                    ]
                ],
                'discounts' => [
                    'giftCardsAmount' => 10
                ],
                'expected' => [
                    'testSku' => 1.66,
                    'shipping' => floatval(0),
                    'discount-rounding-correction' => 0.02,
                ]
            ],
            'Product + shipping: 20 € discount' => [
                'items' => [
                    [
                        'code' => 'testSku',
                        'price' => floatval(30),
                        'amount' => 1
                    ],
                    [
                        'code' => 'shipping',
                        'price' => floatval(5),
                        'amount' => 1
                    ]
                ],
                'discounts' => [
                    'giftCardsAmount' => 20
                ],
                'expected' => [
                    'testSku' => 12.86,
                    'shipping' => 2.14,
                ]
            ],
            'Two separate products one with 0 price + shipping: 20 € discount' => [
                'items' => [
                    [
                        'code' => 'testSku',
                        'price' => floatval(30),
                        'amount' => 1
                    ],
                    [
                        'code' => 'testSku2',
                        'price' => floatval(0),
                        'amount' => 1
                    ],
                    [
                        'code' => 'shipping',
                        'price' => floatval(5),
                        'amount' => 1
                    ]
                ],
                'discounts' => [
                    'giftCardsAmount' => 20
                ],
                'expected' => [
                    'testSku' => 12.86,
                    'testSku2' => 0,
                    'shipping' => 2.14,
                ]
            ],
            'Two separate products + shipping, 7 € discount' => [
                'items' => [
                    [
                        'code' => 'testSku',
                        'price' => floatval(30),
                        'amount' => 1
                    ],
                    [
                        'code' => 'testSku2',
                        'price' => floatval(25),
                        'amount' => 1
                    ],
                    [
                        'code' => 'shipping',
                        'price' => floatval(5),
                        'amount' => 1
                    ]
                ],
                'discounts' => [
                    'giftCardsAmount' => 7
                ],
                'expected' => [
                    'testSku' => 26.50,
                    'testSku2' => 22.08,
                    'shipping' => 4.42,
                ]
            ],
            'Two same products + shipping: 10 € discount' => [
                'items' => [
                    [
                        'code' => 'testSku',
                        'price' => floatval(30),
                        'amount' => 2
                    ],
                    [
                        'code' => 'shipping',
                        'price' => 5.90,
                        'amount' => 1
                    ]
                ],
                'discounts' => [
                    'giftCardsAmount' => 10
                ],
                'expected' => [
                    'testSku' => 25.45,
                    'shipping' => 5,
                ]
            ],
            'Two same products + shipping: 25.50 € discount' => [
                'items' => [
                    [
                        'code' => 'testSku',
                        'price' => floatval(30),
                        'amount' => 2
                    ],
                    [
                        'code' => 'shipping',
                        'price' => 5.90,
                        'amount' => 1
                    ]
                ],
                'discounts' => [
                    'giftCardsAmount' => 10,
                    'customerBalanceAmount' => 15.50,
                ],
                'expected' => [
                    'testSku' => 18.39,
                    'shipping' => 3.62,
                ]
            ],
            'one product, high count, 10 € discount' => [
                'items' => [
                    [
                        'code' => 'testSku1',
                        'price' => 1.25,
                        'amount' => 175,
                    ],
                    [
                        'code' => 'shipping',
                        'price' => 10.90,
                        'amount' => 1
                    ],
                ],
                'discounts' => [
                    'giftCardsAmount' => 10,
                ],
                'expected' => [
                    'testSku1' => 1.20,
                    'shipping' => 9.65
                ]
            ],
            'one product, high count, 43.50 € discount' => [
                'items' => [
                    [
                        'code' => 'testSku1',
                        'price' => 1.45,
                        'amount' => 175,
                    ],
                    [
                        'code' => 'shipping',
                        'price' => 10.90,
                        'amount' => 1
                    ],
                ],
                'discounts' => [
                    'giftCardsAmount' => 43.35,
                ],
                'expected' => [
                    'testSku1' => 1.21,
                    'shipping' => 9.12,
                    'discount-rounding-correction' => 0.43,
                ]
            ],
            'two different products high count, 15€ discount' => [
                'items' => [
                    [
                        'code' => 'testSku1',
                        'price' => 1.90,
                        'amount' => 250,
                    ],
                    [
                        'code' => 'testSku2',
                        'price' => 0.6,
                        'amount' => 25
                    ],
                    [
                        'code' => 'shipping',
                        'price' => 10.90,
                        'amount' => 1
                    ],
                ],
                'discounts' => [
                    'giftCardsAmount' => 15,
                ],
                'expected' => [
                    'testSku1' => 1.84,
                    'testSku2' => 0.59,
                    'shipping' => 10.57,
                    'discount-rounding-correction' => 0.58
                ]
            ],
            'Over thousand products and very expensive shipping' => [
                'items' => [
                    [
                        'code' => 'testSku1',
                        'price' => 2.50,
                        'amount' => 1000,
                    ],
                    [
                        'code' => 'testSku2',
                        'price' => 51,
                        'amount' => 1
                    ],
                    [
                        'code' => 'shipping',
                        'price' => 1001,
                        'amount' => 1
                    ],
                ],
                'discounts' => [
                    'giftCardsAmount' => 15,
                ],
                'expected' => [
                    'testSku1' => 2.49,
                    'testSku2' => 50.22,
                    'shipping' => 996.78,
                ]
            ],
        ];
    }
}
