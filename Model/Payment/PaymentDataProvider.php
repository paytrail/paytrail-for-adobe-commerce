<?php

namespace Paytrail\PaymentService\Model\Payment;

use Magento\Directory\Api\CountryInformationAcquirerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Sales\Model\ResourceModel\Order\Tax\Item as TaxItem;
use Magento\Tax\Helper\Data as TaxHelper;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Logger\PaytrailLogger;
use Paytrail\PaymentService\Model\Company\CompanyRequestData;
use Paytrail\PaymentService\Model\Config\Source\CallbackDelay;
use Paytrail\PaymentService\Model\FinnishReferenceNumber;
use Paytrail\PaymentService\Model\Invoice\Activation\Flag;
use Paytrail\PaymentService\Model\UrlDataProvider;
use Paytrail\SDK\Exception\ValidationException;
use Paytrail\SDK\Model\Address;
use Paytrail\SDK\Model\Customer;
use Paytrail\SDK\Model\Item;
use Paytrail\SDK\Request\AbstractPaymentRequest;
use Paytrail\SDK\Request\PaymentRequest;

class PaymentDataProvider
{
    /**
     * PaymentDataProvider constructor.
     *
     * @param CompanyRequestData $companyRequestData
     * @param CountryInformationAcquirerInterface $countryInfo
     * @param TaxHelper $taxHelper
     * @param DiscountSplitter $discountSplitter
     * @param TaxItem $taxItems
     * @param UrlDataProvider $urlDataProvider
     * @param CallbackDelay $callbackDelay
     * @param FinnishReferenceNumber $referenceNumber
     * @param Config $gatewayConfig
     * @param Flag $flag
     * @param PaytrailLogger $log
     */
    public function __construct(
        private CompanyRequestData                  $companyRequestData,
        private CountryInformationAcquirerInterface $countryInfo,
        private TaxHelper                           $taxHelper,
        private DiscountSplitter                    $discountSplitter,
        private TaxItem                             $taxItems,
        private UrlDataProvider                     $urlDataProvider,
        private CallbackDelay                       $callbackDelay,
        private FinnishReferenceNumber              $referenceNumber,
        private Config                              $gatewayConfig,
        private Flag                                $flag,
        private PaytrailLogger                      $log
    ) {
    }

    /**
     * SetPaymentRequestData function
     *
     * @param AbstractPaymentRequest $paytrailPayment
     * @param Order $order
     * @param string $paymentMethod
     *
     * @return AbstractPaymentRequest
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws CheckoutException
     * @throws ValidationException
     */
    public function setPaymentRequestData(
        AbstractPaymentRequest $paytrailPayment,
        Order                  $order,
        string                 $paymentMethod
    ): AbstractPaymentRequest {

        $billingAddress   = $order->getBillingAddress() ?? $order->getShippingAddress();
        $shippingAddress  = $order->getShippingAddress();
        $customer         = $this->createCustomer($billingAddress);
        $reference        = $this->referenceNumber->getReference($order);
        $invoicingAddress = $this->createAddress($billingAddress);
        $items            = $this->getOrderItemLines($order);

        $paytrailPayment->setStamp($this->getStamp($order))
            ->setReference($reference)
            ->setCurrency($order->getOrderCurrencyCode())
            ->setAmount(round($order->getGrandTotal() * 100))
            ->setCustomer($customer)
            ->setInvoicingAddress($invoicingAddress)
            ->setLanguage($this->gatewayConfig->getStoreLocaleForPaymentProvider())
            ->setItems($items)
            ->setRedirectUrls($this->urlDataProvider->createRedirectUrl())
            ->setCallbackUrls($this->urlDataProvider->createCallbackUrl())
            ->setCallbackDelay($this->callbackDelay->getCallbackDelay());

        if ($shippingAddress !== null) {
            $deliveryAddress = $this->createAddress($shippingAddress);
            $paytrailPayment->setDeliveryAddress($deliveryAddress);
        }

        // Conditionally set manual invoicing flag if selected payment method supports it.
        $this->flag->setManualInvoiceActivationFlag(
            $paytrailPayment,
            $paymentMethod,
            $order
        );

        // Log payment data
        $this->log->debugLog('request', $paytrailPayment);

        return $paytrailPayment;
    }

    /**
     * SetPayAndAddCardRequestData function.
     *
     * @param PaymentRequest $paytrailPayment
     * @param Order $order
     *
     * @return PaymentRequest
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws CheckoutException
     * @throws ValidationException
     */
    public function setPayAndAddCardRequestData(AbstractPaymentRequest $paytrailPayment, $order): AbstractPaymentRequest
    {
        // Set payment request data - payment method is not needed for pay and add card, so we can set it to new string
        // to mach manual invoicing flag condition
        $this->setPaymentRequestData($paytrailPayment, $order, 'pay_and_add_card');

        $paytrailPayment->setCallbackUrls($this->urlDataProvider->createPayAndAddCardCallbackUrl());

        // Log payment data
        $this->log->debugLog('request', $paytrailPayment);

        return $paytrailPayment;
    }

    /**
     * CreateCustomer function
     *
     * @param OrderAddressInterface $billingAddress
     *
     * @return Customer
     */
    private function createCustomer($billingAddress)
    {
        $customer = new Customer();

        $customer->setEmail($billingAddress->getEmail())
            ->setFirstName($billingAddress->getFirstName())
            ->setLastName($billingAddress->getLastname())
            ->setPhone($billingAddress->getTelephone());

        $this->companyRequestData->setCompanyRequestData($customer, $billingAddress);

        return $customer;
    }

    /**
     * CreateAddress function
     *
     * @param Order\Address $address
     *
     * @return Address
     * @throws NoSuchEntityException
     * @throws ValidationException
     */
    private function createAddress($address)
    {
        $paytrailAddress = new Address();

        $country = $this->countryInfo->getCountryInfo($address->getCountryId())
            ->getTwoLetterAbbreviation();

        $streetAddressRows = $address->getStreet();
        $streetAddress     = $streetAddressRows[0];
        if (mb_strlen($streetAddress, 'utf-8') > 50) {
            $streetAddress = mb_substr($streetAddress, 0, 50, 'utf-8');
        }

        $paytrailAddress->setStreetAddress($streetAddress)
            ->setPostalCode($address->getPostcode())
            ->setCity($address->getCity())
            ->setCountry($country);

        if (!empty($address->getRegion())) {
            $paytrailAddress->setCounty($address->getRegion());
        }

        return $paytrailAddress;
    }

    /**
     * GetOrderItemLines function
     *
     * @param Order $order
     *
     * @return array|Item[]
     * @throws LocalizedException
     */
    public function getOrderItemLines($order)
    {
        $orderItems = $this->itemArgs($order);

        return array_map(
            function ($item) {
                return $this->createOrderItems($item);
            },
            $orderItems
        );
    }

    /**
     * CreateOrderItems function
     *
     * @param array $item
     *
     * @return Item
     */
    private function createOrderItems($item): Item
    {
        $paytrailItem = new Item();

        $paytrailItem->setUnitPrice(round($item['price'] * 100))
            ->setUnits($item['amount'])
            ->setVatPercentage($item['vat'])
            ->setProductCode($item['code'])
            ->setDeliveryDate(date('Y-m-d'))
            ->setDescription($item['title']);

        return $paytrailItem;
    }

    /**
     * ItemArgs function
     *
     * @param Order $order
     *
     * @return array
     * @throws LocalizedException
     */
    private function itemArgs($order): array
    {
        $items = [];

        # Add line items
        foreach ($order->getAllItems() as $item) {
            $discountInclTax = 0;
            if (!$this->taxHelper->priceIncludesTax()
                && $this->taxHelper->applyTaxAfterDiscount()
            ) {
                $discountInclTax = $this->formatPrice(
                    $item->getDiscountAmount() * (($item->getTaxPercent() / 100) + 1)
                );
            } else {
                $discountInclTax += $item->getDiscountAmount();
            }

            $qtyOrdered = $item->getQtyOrdered();
            if (!$qtyOrdered) {
                // Prevent division by zero errors
                throw new LocalizedException(\__('Quantity missing for order item: %sku', ['sku' => $item->getSku()]));
            }

            // When in grouped or bundle product price is dynamic (product_calculations = 0)
            // then also the child products has prices so we set
            if ($item->getChildrenItems() && !$item->getProductOptions()['product_calculations']) {
                $items[] = [
                    'title'  => $item->getName(),
                    'code'   => $item->getSku(),
                    'amount' => $qtyOrdered,
                    'price'  => 0,
                    'vat'    => 0
                ];
            } else {
                $rowTotalInclDiscount  = $item->getRowTotalInclTax() - $discountInclTax;
                $itemPriceInclDiscount = $this->formatPrice($rowTotalInclDiscount / $qtyOrdered);

                $difference = $rowTotalInclDiscount - (float)$itemPriceInclDiscount * $qtyOrdered;
                // deduct/add only 0.01 per product
                $diffAdjustment       = 0.01;
                $differenceUnitsCount = (int)(round(abs($difference / $diffAdjustment)));

                if ($differenceUnitsCount > $qtyOrdered) {
                    throw new LocalizedException(
                        \__('Rounding diff bigger than 0.01 per item : %sku', ['sku' => $item->getSku()])
                    );
                }

                $paytrailItem = [
                    'title'  => $item->getName(),
                    'code'   => $item->getSku(),
                    'amount' => $qtyOrdered,
                    'price'  => $itemPriceInclDiscount,
                    'vat'    => $item->getTaxPercent()
                ];

                if ($differenceUnitsCount) {

                    $paytrailItem['amount'] = $qtyOrdered - $differenceUnitsCount;

                    $paytrailItemRoundingCorrection = [
                        'title'  => $item->getName()
                            . ' (rounding issue fix, diff: '
                            . $this->formatPrice($difference)
                            . ')',
                        'code'   => $item->getSku(),
                        'amount' => $differenceUnitsCount,
                        'price'  => $this->formatPrice(
                            floatval($itemPriceInclDiscount) + ($difference <=> 0) * $diffAdjustment
                        ),
                        'vat'    => $item->getTaxPercent()
                    ];

                    $items [] = $paytrailItemRoundingCorrection;
                }

                $items [] = $paytrailItem;
            }
        }

        // Add shipping
        if (!$order->getIsVirtual()) {
            $items[] = $this->getShippingItem($order);
        }

        return $this->discountSplitter->process($items, $order);
    }

    /**
     * GetShippingItem function
     *
     * @param Order $order
     *
     * @return array
     */
    private function getShippingItem(Order $order): array
    {
        $taxDetails = [];
        $price      = 0;

        if ($order->getShippingAmount()) {
            foreach ($this->taxItems->getTaxItemsByOrderId($order->getId()) as $detail) {
                if (isset($detail['taxable_item_type']) && $detail['taxable_item_type'] == 'shipping') {
                    $taxDetails = $detail;
                    break;
                }
            }

            $price = $order->getShippingAmount();
            $price -= $order->getShippingDiscountAmount();
            $price += $order->getShippingTaxAmount();
            $price += $order->getShippingDiscountTaxCompensationAmount();
        }

        return [
            'title'  => $order->getShippingDescription() ?: __('Shipping'),
            'code'   => 'shipping-row',
            'amount' => 1,
            'price'  => floatval($price),
            'vat'    => $taxDetails['tax_percent'] ?? 0,
        ];
    }

    /**
     * Getting stamp for payment request using algorithm defined in config
     *
     * @param Order $order
     *
     * @return string
     */
    public function getStamp(Order $order): string
    {
        return hash($this->gatewayConfig->getCheckoutAlgorithm(), time() . $order->getIncrementId());
    }

    /**
     * Format price.
     *
     * @param  $amount
     *
     * @return string
     */
    private function formatPrice($amount): string
    {
        return number_format(floatval($amount), 2, '.', '');
    }
}

