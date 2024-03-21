<?php

namespace Paytrail\PaymentService\Model\Token;

use Magento\Directory\Api\CountryInformationAcquirerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\ResourceModel\Order\Tax\Item as TaxItem;
use Magento\SalesRule\Model\DeltaPriceRound;
use Magento\Tax\Helper\Data as TaxHelper;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Logger\PaytrailLogger;
use Paytrail\PaymentService\Model\Company\CompanyRequestData;
use Paytrail\PaymentService\Model\Config\Source\CallbackDelay;
use Paytrail\PaymentService\Model\FinnishReferenceNumber;
use Paytrail\PaymentService\Model\Invoice\Activation\Flag;
use Paytrail\PaymentService\Model\Payment\DiscountSplitter;
use Paytrail\PaymentService\Model\Payment\PaymentDataProvider;
use Paytrail\PaymentService\Model\UrlDataProvider;
use PHPUnit\Framework\TestCase;

class RequestDataTest extends TestCase
{
    private PaymentDataProvider                                        $requestDataObject;
    private \Magento\Framework\TestFramework\Unit\Helper\ObjectManager $objectManager;

    protected function setUp(): void
    {
        $this->objectManager = new \Magento\Framework\TestFramework\Unit\Helper\ObjectManager($this);
    }

    /**
     * @param $input
     *
     * @return void
     */
    public function prepareRequestDataMock($input): void
    {
        $config    = $this->objectManager->getObject(Config::class);
        $taxHelper = $this->createMock(TaxHelper::class);
        $taxHelper->method('applyTaxAfterDiscount')->willReturn(
            $input['config']['discount_tax']
        );
        $taxHelper->method('priceIncludesTax')->willReturn(
            $input['config']['catalog_price_includes_tax'] ?? true
        );

        $priceCurrency = $this->getMockForAbstractClass(PriceCurrencyInterface::class);
        $priceCurrency->method('round')
            ->willReturnCallback(
                function ($amount) {
                    return round($amount, 2);
                }
            );
        $configMock = $this->getMockForAbstractClass(ScopeConfigInterface::class);
        $configMock->method('getValue')->willReturn(24);

        $discountSplitter = new DiscountSplitter(
            new DeltaPriceRound($priceCurrency),
            $configMock
        );

        $taxItems = $this->createMock(TaxItem::class);
        $taxItems->method('getTaxItemsByOrderId')->willReturn([]);
        $this->requestDataObject = new PaymentDataProvider(
            $this->createMock(CompanyRequestData::class),
            $this->createMock(CountryInformationAcquirerInterface::class),
            $taxHelper,
            $discountSplitter,
            $taxItems,
            $this->createMock(UrlDataProvider::class),
            $this->createMock(CallbackDelay::class),
            $this->createMock(FinnishReferenceNumber::class),
            $config,
            $this->createMock(Flag::class),
            $this->createMock(PaytrailLogger::class)
        );
    }

    /**
     * @dataProvider itemArgsDataProvider
     * @return void
     * @magentoConfigFixture tax/calculation/apply_after_discount 1
     * @throws LocalizedException
     */
    public function testItemArgsDiscountTax($input, $discounts, $expected)
    {
        $this->prepareRequestDataMock($input);


        /** Mock Order */
        //Add a magic method to the list of mocked class methods
        $orderMethods = \array_merge(
            \get_class_methods(Order::class),
            ['getGiftCardsAmount']
        );


        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->addMethods( ['getGiftCardsAmount'])
            ->onlyMethods(['getShippingAmount', 'getGrandTotal', 'getShippingTaxAmount','getAllItems'])
            ->getMock();

        $items = $this->prepareOrderItemsMock($input['items']);
        $order->setItems($items);
        $order->method('getAllItems')->willReturn(
            $items
        );
        $order->setData($input['order']);
        $order->method('getShippingAmount')->willReturn($input['order']['shipping_amount']);
        $order->method('getGrandTotal')->willReturn($expected['total']);
        $order->method('getShippingTaxAmount')->willReturn($input['order']['shipping_tax_amount']);
        $order->method('getGiftCardsAmount')->willReturn($discounts['giftcard']);

        $paytrailItems = $this->requestDataObject->getOrderItemLines($order);

        $this->assertEquals(
            number_format($expected['total'] * 100, 0, '.', ''),
            array_reduce($paytrailItems, fn($carry, $item) => $carry + $item->getUnitPrice() * $item->getUnits(), 0),
            'Total does not match'
        );
    }

    /**
     * @return array[]
     */
    public static function itemArgsDataProvider(): array
    {
        $cases = [
            '#1 discount 10.00, giftcard 10.00'   => [
                'price'          => 100,
                'qty'            => 3,
                'discount'       => 10.00,
                'giftcard'       => 10.00,
                'discount_tax'   => 1,
                'shipping_tax'   => 1,
                'expected_total' => 294.90,
            ],
            '#2 discount 10.00 , giftcard 0'      => [
                'price'          => 100,
                'qty'            => 3,
                'discount'       => 10.00,
                'giftcard'       => 0.00,
                'discount_tax'   => 1,
                'shipping_tax'   => 1,
                'expected_total' => 304.90,
            ],
            '#3 discount 10.00 , giftcard 0 '     => [
                'price'        => 100,
                'qty'          => 3,
                'discount'     => 0.00,
                'giftcard'     => 10.00,
                'discount_tax' => 1,
                'shipping_tax' => 1,
            ],
            '#4 discount 0 , giftcard 0'          => [
                'price'        => 100,
                'qty'          => 3,
                'discount'     => 0.00,
                'giftcard'     => 0.00,
                'discount_tax' => 1,
                'shipping_tax' => 1,
            ],
            '#5 discount 10.01 , giftcard 0'      => [
                'price'                      => 124,
                'qty'                        => 3,
                'discount'                   => 10.00,
                'giftcard'                   => 0,
                'catalog_price_includes_tax' => 0,
                'discount_tax'               => 1,
                'shipping_tax'               => 1,
                'expected_total'             => 374.50,
            ],
            '#6 discount 10.01 , giftcard 10.01'  => [
                'price'        => 100,
                'qty'          => 3,
                'discount'     => 10.01,
                'giftcard'     => 10.01,
                'discount_tax' => 1,
                'shipping_tax' => 1,
            ],
            '#7 discount 0 , giftcard 10.01'      => [
                'price'        => 100,
                'qty'          => 3,
                'discount'     => 0,
                'giftcard'     => 10.01,
                'discount_tax' => 1,
                'shipping_tax' => 1,
            ],
            '#8 discount 0 , giftcard 10.01'      => [
                'price'        => 100,
                'qty'          => 3,
                'discount'     => 10.00,
                'giftcard'     => 10.01,
                'discount_tax' => 0,
                'shipping_tax' => 1,
            ],
            '#9 discount 10.01 , giftcard 10.01'  => [
                'price'        => 100,
                'qty'          => 3,
                'discount'     => 10.01,
                'giftcard'     => 0,
                'discount_tax' => 0,
                'shipping_tax' => 0,
            ],
            '#10 discount 10.01 , giftcard 10.01' => [
                'price'        => 124,
                'qty'          => 2,
                'discount'     => 10.01,
                'giftcard'     => 10.01,
                'discount_tax' => 0,
                'shipping_tax' => 0,
            ],
        ];

        $result = [];
        foreach ($cases as $key => $case) {
            $shippingExclTax = 12.02;
            $shippingTax     = $case['shipping_tax'] ? $shippingExclTax * 0.24 : 0;
            $discountTax     = $case['discount_tax'] ? $case['discount'] * 0.24 : 0;
            $expectedTotal   = $case['price'] * $case['qty'] - $case['discount'] - $discountTax - $case['giftcard'] + $shippingExclTax + $shippingTax;
            $result[$key]    = [
                'input'     => [
                    'config'   => [
                        'discount_tax'               => $case['discount_tax'],
                        'shipping_tax'               => $case['shipping_tax'],
                        'catalog_price_includes_tax' => $case['catalog_price_includes_tax'] ?? true,
                    ],
                    'shipping' => $shippingExclTax,
                    'items'    => [
                        [
                            'qty'             => 3,
                            'price'           => $case['price'],
                            'tax_percent'     => 24,
                            'row_total'       => $case['price'] * $case['qty'],
                            'name'            => 'test',
                            'discount_amount' => $case['discount'],
                        ],
                    ],
                    'order'    =>
                        [
                            'discount_amount'                           => $case['discount'],
                            'shipping_amount'                           => $shippingExclTax,
                            'shipping_tax_amount'                       => $shippingTax,
                            'shipping_discount_amount'                  => 0,
                            'shipping_discount_tax_compensation_amount' => 0,
                        ]

                ],
                'discounts' => [
                    'giftcard' => $case['giftcard'],
                ],
                'expected'  => [
                    'total' => $case['expected_total']
                        ?? $expectedTotal
                ],
            ];
        }

        return $result;
    }

    private function prepareOrderItemsMock($items)
    {
        $orderItems = [];
        foreach ($items as $item) {
            $orderItem = $this->createMock(Item::class);
            $orderItem->method('getQtyOrdered')->willReturn($item['qty']);
            $orderItem->method('getPriceInclTax')->willReturn($item['price']);
            $orderItem->method('getTaxPercent')->willReturn($item['tax_percent']);
            $orderItem->method('getBasePriceInclTax')->willReturn($item['price']);
            $orderItem->method('getRowTotalInclTax')->willReturn($item['row_total']);
            $orderItem->method('getDiscountAmount')->willReturn($item['discount_amount']);
            $orderItems[] = $orderItem;
        }
        return $orderItems;
    }
}
