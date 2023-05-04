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
use Paytrail\PaymentService\Helper\Data as Helper;
use Paytrail\PaymentService\Logger\PaytrailLogger;
use Paytrail\PaymentService\Model\Company\CompanyRequestData;
use Paytrail\PaymentService\Model\Config\Source\CallbackDelay;
use Paytrail\PaymentService\Model\UrlDataProvider;
use Paytrail\SDK\Model\Address;
use Paytrail\SDK\Model\Customer;
use Paytrail\SDK\Model\Item;
use Paytrail\SDK\Request\PaymentRequest;

class PaymentDataProvider
{
    /**
     * PaymentDataProvider constructor.
     *
     * @param Helper $helper
     * @param CompanyRequestData $companyRequestData
     * @param CountryInformationAcquirerInterface $countryInfo
     * @param TaxHelper $taxHelper
     * @param DiscountSplitter $discountSplitter
     * @param TaxItem $taxItems
     * @param UrlDataProvider $urlDataProvider
     * @param CallbackDelay $callbackDelay
     * @param PaytrailLogger $log
     */
    public function __construct(
        private Helper                              $helper,
        private CompanyRequestData                  $companyRequestData,
        private CountryInformationAcquirerInterface $countryInfo,
        private TaxHelper                           $taxHelper,
        private DiscountSplitter                    $discountSplitter,
        private TaxItem                             $taxItems,
        private UrlDataProvider                     $urlDataProvider,
        private CallbackDelay                       $callbackDelay,
        private PaytrailLogger                      $log
    ) {
    }

    /**
     * SetPaymentRequestData function
     *
     * @param PaymentRequest $paytrailPayment
     * @param Order $order
     * @return PaymentRequest
     * @throws NoSuchEntityException
     */
    public function setPaymentRequestData(PaymentRequest $paytrailPayment, $order): PaymentRequest
    {
        $billingAddress = $order->getBillingAddress() ?? $order->getShippingAddress();
        $shippingAddress = $order->getShippingAddress();

        $paytrailPayment->setStamp(hash('sha256', time() . $order->getIncrementId()));

        $reference = $this->helper->getReference($order);

        $paytrailPayment->setReference($reference);

        $paytrailPayment->setCurrency($order->getOrderCurrencyCode())->setAmount(round($order->getGrandTotal() * 100));

        $customer = $this->createCustomer($billingAddress);
        $paytrailPayment->setCustomer($customer);

        $invoicingAddress = $this->createAddress($billingAddress);
        $paytrailPayment->setInvoicingAddress($invoicingAddress);

        if ($shippingAddress !== null) {
            $deliveryAddress = $this->createAddress($shippingAddress);
            $paytrailPayment->setDeliveryAddress($deliveryAddress);
        }

        $paytrailPayment->setLanguage($this->helper->getStoreLocaleForPaymentProvider());

        $items = $this->getOrderItemLines($order);

        $paytrailPayment->setItems($items);

        $paytrailPayment->setRedirectUrls($this->urlDataProvider->createRedirectUrl());

        $paytrailPayment->setCallbackUrls($this->urlDataProvider->createCallbackUrl());

        $paytrailPayment->setCallbackDelay($this->callbackDelay->getCallbackDelay());

        // Log payment data
        $this->log->debugLog('request', $paytrailPayment);

        return $paytrailPayment;
    }

    /**
     * CreateCustomer function
     *
     * @param OrderAddressInterface $billingAddress
     * @return Customer
     */
    protected function createCustomer($billingAddress)
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
     * @return Address
     * @throws NoSuchEntityException
     * @throws \Paytrail\SDK\Exception\ValidationException
     */
    protected function createAddress($address)
    {
        $paytrailAddress = new Address();

        $country = $this->countryInfo->getCountryInfo(
            $address->getCountryId()
        )
            ->getTwoLetterAbbreviation();
        $streetAddressRows = $address->getStreet();
        $streetAddress = $streetAddressRows[0];
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
     * @return array|Item[]
     * @throws LocalizedException
     */
    protected function getOrderItemLines($order)
    {
        $orderItems = $this->itemArgs($order);
        $orderTotal = round($order->getGrandTotal() * 100);

        $items = array_map(
            function ($item) use ($order) {
                return $this->createOrderItems($item);
            },
            $orderItems
        );

        $itemSum = 0;
        $itemQty = 0;

        /** @var Item $orderItem */
        foreach ($items as $orderItem) {
            $itemSum += floatval($orderItem->getUnitPrice() * $orderItem->getUnits());
            $itemQty += $orderItem->getUnits();
        }

        if ($itemSum != $orderTotal) {
            $diffValue = abs($itemSum - $orderTotal);

            if ($diffValue > $itemQty) {
                throw new \Exception(__(
                    'Difference in rounding the prices is too big ' . $orderTotal . ' --- ' . $itemSum
                ));
            }

            $roundingItem = new Item();
            $roundingItem->setDescription(__('Rounding', 'paytrail-for-adobe-commerce'));
            $roundingItem->setDeliveryDate(date('Y-m-d'));
            $roundingItem->setVatPercentage(0);
            $roundingItem->setUnits(($orderTotal - $itemSum > 0) ? 1 : -1);
            $roundingItem->setUnitPrice($diffValue);
            $roundingItem->setProductCode('rounding-row');

            $items[] = $roundingItem;
        }
        return $items;
    }

    /**
     * CreateOrderItems function
     *
     * @param array $item
     * @return Item
     */
    protected function createOrderItems($item): Item
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
     * @return array
     * @throws LocalizedException
     */
    protected function itemArgs($order): array
    {
        $items = [];

        # Add line items
        /** @var $item OrderItem */
        foreach ($order->getAllItems() as $item) {
            $discountIncl = 0;
            if (!$this->taxHelper->priceIncludesTax()) {
                $discountIncl += $item->getDiscountAmount() * (($item->getTaxPercent() / 100) + 1);
            } else {
                $discountIncl += $item->getDiscountAmount();
            }

            if (!$item->getQtyOrdered()) {
                // Prevent division by zero errors
                throw new LocalizedException(\__('Quantity missing for order item: %sku', ['sku' => $item->getSku()]));
            }

            // When in grouped or bundle product price is dynamic (product_calculations = 0)
            // then also the child products has prices so we set
            if ($item->getChildrenItems() && !$item->getProductOptions()['product_calculations']) {
                $items[] = [
                    'title' => $item->getName(),
                    'code' => $item->getSku(),
                    'amount' => floatval($item->getQtyOrdered()),
                    'price' => 0,
                    'vat' => 0
                ];
            } else {
                $items[] = [
                    'title' => $item->getName(),
                    'code' => $item->getSku(),
                    'amount' => floatval($item->getQtyOrdered()),
                    'price' => floatval($item->getPriceInclTax()) - round(($discountIncl / $item->getQtyOrdered()), 2),
                    'vat' => round(floatval($item->getTaxPercent()))
                ];
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
     * @return array
     */
    private function getShippingItem(Order $order): array
    {
        $taxDetails = [];
        $price = 0;

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
            'title' => $order->getShippingDescription() ?: __('Shipping'),
            'code' => 'shipping-row',
            'amount' => 1,
            'price' => floatval($price),
            'vat' => $taxDetails['tax_percent'] ?? 0,
        ];
    }
}
