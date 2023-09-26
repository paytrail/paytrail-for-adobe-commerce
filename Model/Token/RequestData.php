<?php
declare(strict_types=1);

namespace Paytrail\PaymentService\Model\Token;

use Magento\Directory\Api\CountryInformationAcquirerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Sales\Model\ResourceModel\Order\Tax\Item as TaxItems;
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Helper\Data;
use Paytrail\PaymentService\Model\FinnishReferenceNumber;
use Paytrail\PaymentService\Model\Payment\DiscountSplitter;
use Paytrail\SDK\Model\Address;
use Paytrail\SDK\Model\CallbackUrl;
use Paytrail\SDK\Model\Customer;
use Paytrail\SDK\Model\Item;

class RequestData
{
    /**
     * @var \Paytrail\PaymentService\Model\FinnishReferenceNumber
     */
    private FinnishReferenceNumber $finnishReferenceNumber;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepositoryInterface;

    /**
     * @var Config
     */
    private $gatewayConfig;

    /**
     * @var Data
     */
    private $helper;

    /**
     * @var TaxHelper
     */
    private $taxHelper;

    /**
     * @var DiscountSplitter
     */
    private $discountSplitter;

    /**
     * @var CountryInformationAcquirerInterface
     */
    private $countryInfo;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var TaxItems
     */
    private $taxItems;

    /**
     * @var PaymentTokenManagementInterface
     */
    private $paymentTokenManagement;

    /**
     * @param OrderRepositoryInterface $orderRepositoryInterface
     * @param Config $gatewayConfig
     * @param Data $helper
     * @param TaxHelper $taxHelper
     * @param DiscountSplitter $discountSplitter
     * @param CountryInformationAcquirerInterface $countryInfo
     * @param UrlInterface $urlBuilder
     * @param RequestInterface $request
     * @param TaxItems $taxItems
     * @param PaymentTokenManagementInterface $paymentTokenManagement
     */
    public function __construct(
        OrderRepositoryInterface $orderRepositoryInterface,
        Config $gatewayConfig,
        Data $helper,
        TaxHelper $taxHelper,
        DiscountSplitter $discountSplitter,
        CountryInformationAcquirerInterface $countryInfo,
        UrlInterface $urlBuilder,
        RequestInterface $request,
        TaxItems $taxItems,
        PaymentTokenManagementInterface $paymentTokenManagement,
        FinnishReferenceNumber $finnishReferenceNumber
    ) {
        $this->orderRepositoryInterface = $orderRepositoryInterface;
        $this->gatewayConfig = $gatewayConfig;
        $this->helper = $helper;
        $this->taxHelper = $taxHelper;
        $this->discountSplitter = $discountSplitter;
        $this->countryInfo = $countryInfo;
        $this->urlBuilder = $urlBuilder;
        $this->request = $request;
        $this->taxItems = $taxItems;
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->finnishReferenceNumber = $finnishReferenceNumber;
    }

    /**
     * @param $paytrailPayment
     * @param $order
     * @param $tokenId
     * @param $rcustomer
     * @return mixed
     * @throws LocalizedException
     */
    public function setTokenPaymentRequestData($paytrailPayment, $order, $tokenId, $rcustomer)
    {
        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();

        $paytrailPayment->setStamp(hash($this->gatewayConfig->getCheckoutAlgorithm(), time() . $order->getIncrementId()));

        $reference = $this->finnishReferenceNumber->getReference($order);

        $paytrailPayment->setReference($reference);

        $paytrailPayment->setCurrency($order->getOrderCurrencyCode())->setAmount((int)round($order->getGrandTotal() * 100));

        /* we should alredy have a customer */
        $customer = $this->createCustomer($billingAddress);

        $customerId = $rcustomer->getId();
        $this->helper->logCheckoutData('request', 'info', 'we have customer:'.$customerId);
        $paytrailPayment->setCustomer($customer);

        $invoicingAddress = $this->createAddress($order, $billingAddress);
        $paytrailPayment->setInvoicingAddress($invoicingAddress);

        if (!is_null($shippingAddress)) {
            $deliveryAddress = $this->createAddress($order, $shippingAddress);
            $paytrailPayment->setDeliveryAddress($deliveryAddress);
        }

        $paytrailPayment->setLanguage($this->helper->getStoreLocaleForPaymentProvider());

        $items = $this->getOrderItemLines($order);

        $paytrailPayment->setItems($items);

        $paytrailPayment->setRedirectUrls($this->createRedirectUrl());

        $paytrailPayment->setCallbackUrls($this->createCallbackUrl());
        // set token
        $token = $this->getPaymentToken($tokenId, $customerId);
        $paymentToken = $token->getGatewayToken();

        $paymentExtensionAttributes = $order->getPayment()->getExtensionAttributes();
        $paymentExtensionAttributes->setVaultPaymentToken($token);
        $this->orderRepositoryInterface->save($order);

        $this->helper->logCheckoutData('request', 'info', 'we have token:'.$paymentToken);
        $paytrailPayment->setToken($paymentToken);

        // Log payment data
        $this->helper->logCheckoutData('request', 'info', $paytrailPayment);

        return $paytrailPayment;
    }

    /**
     * @param $order
     * @param $address
     * @return Address
     */
    public function createAddress($order, $address)
    {
        $opAddress = new Address();

        $country = $this->countryInfo->getCountryInfo(
            $address->getCountryId()
        )
            ->getTwoLetterAbbreviation();
        $streetAddressRows = $address->getStreet();
        $streetAddress = $streetAddressRows[0];
        if (mb_strlen($streetAddress, 'utf-8') > 50) {
            $streetAddress = mb_substr($streetAddress, 0, 50, 'utf-8');
        }

        $opAddress->setStreetAddress($streetAddress)
            ->setPostalCode($address->getPostcode())
            ->setCity($address->getCity())
            ->setCountry($country);

        if (!empty($address->getRegion())) {
            $opAddress->setCounty($address->getRegion());
        }

        return $opAddress;
    }

    /**
     * @param $billingAddress
     * @return Customer
     */
    public function createCustomer($billingAddress)
    {
        $customer = new Customer();

        $customer->setEmail($billingAddress->getEmail())
            ->setFirstName($billingAddress->getFirstName())
            ->setLastName($billingAddress->getLastname())
            ->setPhone($billingAddress->getTelephone());

        return $customer;
    }

    /**
     * @param $order
     * @return Item
     */
    protected function getOrderItem($order)
    {
        return $this->createOrderItems($order);
    }

    /**
     * @param Order $order
     * @return array
     * @throws LocalizedException
     */
    public function getOrderItemLines(Order $order): array
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
            $diffValue = (int) abs($itemSum - $orderTotal);

            if ($diffValue > $itemQty) {
                throw new LocalizedException(__('Difference in rounding the prices is too big'));
            }

            $roundingItem = new Item();
            $roundingItem->setDescription((string)__('Rounding', 'op-payment-service-magento-2'));
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
     * @param $order
     * @return mixed
     * @throws LocalizedException
     */
    protected function itemArgs($order)
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
                $paytrailItem = [
                    'title' => $item->getName(),
                    'code' => $item->getSku(),
                    'amount' => floatval($item->getQtyOrdered()),
                    'price' => floatval($item->getPriceInclTax()) - round(($discountIncl / $item->getQtyOrdered()), 2),
                    'vat' => round(floatval($item->getTaxPercent()))
                ];

                $difference = $discountIncl - round(
                    abs($paytrailItem['amount'] * $paytrailItem['price'] - $item->getRowTotalInclTax()),
                    2
                    );
                if ($difference) {
                    $paytrailItem['amount'] -= 1;
                    $paytrailItemDiscountCorrection = $paytrailItem;
                    $paytrailItemDiscountCorrection['amount'] = 1;
                    $paytrailItemDiscountCorrection['price'] -= $difference;
                }

                $items [] = $paytrailItem;
                if ($difference) {
                    $items [] = $paytrailItemDiscountCorrection;
                }
            }
        }

        // Add shipping
        if (!$order->getIsVirtual()) {
            $items[] = $this->getShippingItem($order);
        }

        return $this->discountSplitter->process($items, $order);
    }

    /**
     * @param array $item
     * @return Item
     */
    protected function createOrderItems(array $item)
    {
        $opItem = new Item();

        $opItem->setUnitPrice((int)($item['price'] * 100))
            ->setUnits((int)$item['amount'])
            ->setVatPercentage((int)$item['vat'])
            ->setProductCode($item['code'])
            ->setDeliveryDate(date('Y-m-d'))
            ->setDescription($item['title']);

        return $opItem;
    }

    /**
     * @param Order $order
     * @return array
     */
    private function getShippingItem(Order $order)
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

    /**
     * @return CallbackUrl
     */
    protected function createRedirectUrl()
    {
        $callback = new CallbackUrl();

        $callback->setSuccess($this->getCallbackUrl('receipt'));
        $callback->setCancel($this->getCallbackUrl('receipt'));

        return $callback;
    }

    /**
     * @return CallbackUrl
     */
    protected function createCallbackUrl()
    {
        $callback = new CallbackUrl();

        $callback->setSuccess($this->getCallbackUrl('callback'));
        $callback->setCancel($this->getCallbackUrl('callback'));

        return $callback;
    }

    /**
     * @param $param
     * @return string
     */
    protected function getCallbackUrl($param)
    {
        $routeParams = [
            '_secure' => $this->request->isSecure(),
        ];

        if ($this->request->getParam('custom_redirect_url')) {
            $routeParams['custom_redirect_url'] = $this->request->getParam('custom_redirect_url');
        }

        return $this->urlBuilder->getUrl('paytrail/' . $param, $routeParams);
    }

    /**
     * @param $tokenhash
     * @param $customerId
     * @return \Magento\Vault\Api\Data\PaymentTokenInterface|null
     */
    protected function getPaymentToken($tokenHash, $customerId)
    {
        $token = $this->paymentTokenManagement->getByPublicHash($tokenHash, $customerId);
        return $token;
    }
}
